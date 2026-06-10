<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\DashboardController;

class DdoController extends Controller
{
    /**
     * Get DDO information for an application (for ddo-change page)
     * GET /api/ddo/by-application/{online_application_id}
     */
    public function getDdoByApplication(Request $request, $onlineApplicationId)
    {
        try {
            $uid = $request->get('uid');
            $username = $request->get('username');

            // Get DDO info from housing database
            $ddoInfo = DB::table('housing_ddo as hd')
                ->join('housing_applicant_official_detail as haod', 'haod.ddo_id', '=', 'hd.ddo_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.online_application_id', $onlineApplicationId)
                ->select(
                    'hd.ddo_id',
                    'hd.ddo_designation',
                    'hd.ddo_address',
                    'hd.ddo_code',
                    'hd.is_active',
                    'haod.applicant_official_detail_id'
                )
                ->first();

            $oldDdo = null;
            if ($ddoInfo) {
                $oldDdo = [
                    'ddo_id' => $ddoInfo->ddo_id,
                    'ddo_designation' => $ddoInfo->ddo_designation,
                    'ddo_address' => $ddoInfo->ddo_address,
                    'ddo_code' => $ddoInfo->ddo_code,
                    'applicant_official_detail_id' => $ddoInfo->applicant_official_detail_id,
                ];
            } else {
                $oldDdo = [
                    'ddo_id' => 0,
                    'ddo_designation' => '',
                    'ddo_address' => '',
                    'ddo_code' => '',
                    'applicant_official_detail_id' => null,
                ];
            }

            // Get current DDO from HRMS
            $currentDdo = null;
            if ($username) {
                $hrmsData = $this->getHRMSUserData($username);
                
                if ($hrmsData && is_array($hrmsData) && isset($hrmsData['ddoId'])) {
                    $currentDdoCode = $hrmsData['ddoId'];
                    \Log::info('HRMS DDO Code fetched', [
                        'username' => $username,
                        'ddo_code' => $currentDdoCode
                    ]);
                    $ddoFromDb = $this->getDdoByCode($currentDdoCode);
                    
                    if ($ddoFromDb) {
                        $currentDdo = [
                            'ddo_id' => $ddoFromDb->ddo_id,
                            'ddo_code' => $ddoFromDb->ddo_code,
                            'ddo_designation' => $ddoFromDb->ddo_designation,
                        ];
                    } else {
                        $currentDdo = [
                            'ddo_id' => 0,
                            'ddo_code' => '<span style="color:red;">DDO code not found in the Housing Department records. Please contact the department for updation.</span>',
                            'ddo_designation' => '',
                        ];
                    }
                } else {
                    $currentDdo = [
                        'ddo_id' => 0,
                        'ddo_code' => '',
                        'ddo_designation' => '',
                    ];
                }
            } else {
                $currentDdo = [
                    'ddo_id' => 0,
                    'ddo_code' => '',
                    'ddo_designation' => '',
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'old_ddo' => $oldDdo,
                    'current_ddo' => $currentDdo,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get DDO By Application Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch DDO information',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update DDO change (submit ddo-change form)
     * POST /api/ddo/update-change
     */
    public function updateDdoChange(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer',
                'old_ddo_id' => 'required|integer',
                'current_ddo_id' => 'nullable|integer',
                'applicant_official_detail_id' => 'nullable|integer',
                'old_ddo_code' => 'required|string',
                'current_ddo_code' => 'required|string',
                'uid' => 'required|integer',
                'agree_declaration' => 'required|accepted'
            ]);

            $onlineApplicationId = $request->online_application_id;
            $oldDdoId = $request->old_ddo_id;
            $currentDdoId = $request->current_ddo_id ?? 0; // Default to 0 if not provided (when DDO not found)
            $applicantOfficialDetailId = $request->applicant_official_detail_id ?? null;
            $oldDdoCode = $request->old_ddo_code;
            $currentDdoCode = $request->current_ddo_code;
            $userId = $request->uid;

            // Remove HTML tags from current_ddo_code for comparison
            $currentDdoCodeClean = strip_tags($currentDdoCode);

            DB::beginTransaction();

            // Check if DDO codes are the same
            if ($currentDdoCodeClean == $oldDdoCode) {
                // Even when codes are the same, update declaration and status (matching Drupal behavior)
                // Update declaration table
                DB::table('housing_declration')
                    ->where('online_application_id', $onlineApplicationId)
                    ->where('uid', $userId)
                    ->update([
                        'ddo_change_date' => now(),
                        'ddo_id_from' => $oldDdoId,
                        'ddo_id_to' => $currentDdoId,
                    ]);

                // Update flat occupant status to Accept
                DB::table('housing_flat_occupant')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['accept_reject_status' => 'Accept']);

                // Update online application status to applicant_acceptance
                DB::table('housing_online_application')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['status' => 'applicant_acceptance']);

                // Insert process flow
                $statusId = $this->getStatusId('applicant_acceptance');
                if ($statusId) {
                    // Check if process flow already exists to avoid duplicates
                    $existingFlow = DB::table('housing_process_flow')
                        ->where('online_application_id', $onlineApplicationId)
                        ->where('short_code', 'applicant_acceptance')
                        ->first();
                    
                    if (!$existingFlow) {
                        DB::table('housing_process_flow')->insert([
                            'online_application_id' => $onlineApplicationId,
                            'status_id' => $statusId,
                            'created_at' => now(),
                            'uid' => $userId,
                            'short_code' => 'applicant_acceptance'
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'DDO details updated successfully and You have accepted the terms and conditions stated in the Declaration before Competent Authority.',
                    'status_code' => 200
                ], 200);
            } else {
                // DDO codes are different - update applicant official detail as well
                // Only update if current_ddo_id is valid (not 0) and applicant_official_detail_id is provided
                if ($currentDdoId > 0 && $applicantOfficialDetailId) {
                    // Update DDO in applicant official detail
                    $updated = DB::table('housing_applicant_official_detail')
                        ->where('applicant_official_detail_id', $applicantOfficialDetailId)
                        ->where('uid', $userId)
                        ->where('ddo_id', $oldDdoId)
                        ->update(['ddo_id' => $currentDdoId]);
                    
                    // Log if update didn't affect any rows
                    if ($updated === 0) {
                        Log::warning('DDO Change: No rows updated in applicant_official_detail', [
                            'applicant_official_detail_id' => $applicantOfficialDetailId,
                            'uid' => $userId,
                            'old_ddo_id' => $oldDdoId,
                            'current_ddo_id' => $currentDdoId
                        ]);
                    }
                }

                // Update declaration table
                DB::table('housing_declration')
                    ->where('online_application_id', $onlineApplicationId)
                    ->where('uid', $userId)
                    ->update([
                        'ddo_change_date' => now(),
                        'ddo_id_from' => $oldDdoId,
                        'ddo_id_to' => $currentDdoId,
                    ]);

                // Update flat occupant status to Accept
                DB::table('housing_flat_occupant')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['accept_reject_status' => 'Accept']);

                // Update online application status to applicant_acceptance
                DB::table('housing_online_application')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['status' => 'applicant_acceptance']);

                // Insert process flow
                $statusId = $this->getStatusId('applicant_acceptance');
                if ($statusId) {
                    // Check if process flow already exists to avoid duplicates
                    $existingFlow = DB::table('housing_process_flow')
                        ->where('online_application_id', $onlineApplicationId)
                        ->where('short_code', 'applicant_acceptance')
                        ->first();
                    
                    if (!$existingFlow) {
                        DB::table('housing_process_flow')->insert([
                            'online_application_id' => $onlineApplicationId,
                            'status_id' => $statusId,
                            'created_at' => now(),
                            'uid' => $userId,
                            'short_code' => 'applicant_acceptance'
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'DDO details updated successfully and You have accepted the terms and conditions stated in the Declaration before Competent Authority.',
                    'status_code' => 200
                ], 200);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Update DDO Change Validation Error', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
                'errors' => $e->errors(),
                'status_code' => 422
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update DDO Change Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update DDO change: ' . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get DDO by code
     */
    protected function getDdoByCode($ddoCode)
    {
        return DB::table('housing_ddo')
            ->where('is_active', 'Y')
            ->whereRaw('TRIM(ddo_code) = ?', [trim($ddoCode)])
            ->select('ddo_id', 'ddo_designation', 'ddo_code')
            ->first();
    }

    /**
     * Get HRMS user data (similar to DashboardController)
     */
    protected function getHRMSUserData($username)
    {
        try {
            $dashboardController = new DashboardController();
            return $dashboardController->getHRMSUserDataBackend($username);
        } catch (\Exception $e) {
            Log::error('Get HRMS User Data Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get status ID by short code
     */
    protected function getStatusId($shortCode)
    {
        $status = DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->first();
        
        return $status ? $status->status_id : null;
    }

    /**
     * Get DDO list (for admin)
     * GET /api/ddo
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('housing_ddo as hd')
                ->join('housing_treasury as ht', 'ht.treasury_id', '=', 'hd.treasury_id')
                ->join('housing_district as hds', 'hds.district_code', '=', 'hd.district_code')
                ->select(
                    'hd.ddo_id',
                    'hd.ddo_designation',
                    'hd.ddo_code',
                    'hd.is_active',
                    'ht.treasury_name',
                    'hds.district_name'
                )
                ->orderBy('hd.ddo_id', 'ASC');

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('hd.ddo_code', 'like', '%' . $search . '%')
                        ->orWhere('hd.ddo_designation', 'like', '%' . $search . '%')
                        ->orWhere('hds.district_name', 'like', '%' . $search . '%')
                        ->orWhere('ht.treasury_name', 'like', '%' . $search . '%');
                });
            }

            $perPage = (int) $request->input('per_page', 10);
            if (!in_array($perPage, [10, 25, 50, 100], true)) {
                $perPage = 10;
            }

            $ddoList = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $ddoList
            ]);

        } catch (\Exception $e) {
            Log::error('Get DDO List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch DDO list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Store new DDO (for admin)
     * POST /api/ddo
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ddo_code' => 'required|string|max:255',
                'ddo_designation' => 'required|string|max:255',
                'district_code' => 'required|integer',
                'treasury_id' => 'required|integer',
                'is_active' => 'required|in:Y,N',
                'ddo_address' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'status_code' => 422
                ], 422);
            }

            // Check for duplicate DDO code
            $existing = DB::table('housing_ddo')
                ->where('ddo_code', $request->ddo_code)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Duplicate DDO Code. Please change DDO Code.',
                    'status_code' => 422
                ], 422);
            }

            $data = [
                'ddo_code' => trim($request->ddo_code),
                'ddo_designation' => trim($request->ddo_designation),
                'district_code' => $request->district_code,
                'treasury_id' => $request->treasury_id,
                'is_active' => $request->is_active,
                'ddo_address' => $request->ddo_address ? trim($request->ddo_address) : null,
            ];

            DB::table('housing_ddo')->insert($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Record added successfully.',
                'status_code' => 201
            ], 201);

        } catch (\Exception $e) {
            Log::error('Store DDO Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add DDO',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get DDO by ID (for admin)
     * GET /api/ddo/{id}
     */
    public function show($id)
    {
        try {
            $ddo = DB::table('housing_ddo as hd')
                ->join('housing_treasury as ht', 'ht.treasury_id', '=', 'hd.treasury_id')
                ->join('housing_district as hds', 'hds.district_code', '=', 'hd.district_code')
                ->where('hd.ddo_id', $id)
                ->select(
                    'hd.*',
                    'ht.treasury_name',
                    'hds.district_name'
                )
                ->first();

            if (!$ddo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'DDO not found',
                    'status_code' => 404
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $ddo
            ]);

        } catch (\Exception $e) {
            Log::error('Get DDO Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch DDO',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update DDO (for admin)
     * PUT /api/ddo/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ddo_code' => 'required|string|max:255',
                'ddo_designation' => 'required|string|max:255',
                'district_code' => 'required|integer',
                'treasury_id' => 'required|integer',
                'is_active' => 'required|in:Y,N',
                'ddo_address' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'status_code' => 422
                ], 422);
            }

            $data = [
                'ddo_code' => trim($request->ddo_code),
                'ddo_designation' => trim($request->ddo_designation),
                'district_code' => $request->district_code,
                'treasury_id' => $request->treasury_id,
                'is_active' => $request->is_active,
                'ddo_address' => $request->ddo_address ? trim($request->ddo_address) : null,
            ];

            DB::table('housing_ddo')
                ->where('ddo_id', $id)
                ->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Record edited successfully.',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Update DDO Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update DDO',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Delete DDO (for admin)
     * DELETE /api/ddo/{id}
     */
    public function destroy($id)
    {
        try {
            DB::table('housing_ddo')
                ->where('ddo_id', $id)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Record deleted successfully.',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Delete DDO Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete DDO',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get treasury by district (for AJAX)
     * GET /api/ddo/treasury-by-district/{district_code}
     */
    public function getTreasuryByDistrict($districtCode)
    {
        try {
            $treasuries = DB::table('housing_treasury')
                ->where('district_code', $districtCode)
                ->orderBy('treasury_id', 'asc')
                ->select('treasury_id', 'treasury_name')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $treasuries
            ]);

        } catch (\Exception $e) {
            Log::error('Get Treasury By District Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch treasuries',
                'status_code' => 500
            ], 500);
        }
    }
}

