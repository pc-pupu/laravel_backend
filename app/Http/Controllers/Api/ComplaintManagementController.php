<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplaintManagementController extends Controller
{
    public function myComplaints(int $uid)
    {
        try {
            $rows = DB::table('housing_online_complaint')
                ->where('uid', $uid)
                ->orderByDesc('online_complaint_id')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $rows,
                'status_code' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('Complaint list fetch failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch complaint list.',
                'status_code' => 500,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'uid' => ['required', 'integer'],
            'complaint_type' => ['required', 'string', 'max:100'],
            'complaint_other_type' => ['nullable', 'string', 'max:100'],
            'complaint_details' => ['required', 'string', 'max:200'],
        ]);

        try {
            $occupant = $this->getEligibleOccupantForUser((int) $data['uid']);
            if (!$occupant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not an eligible occupant to submit a complaint.',
                    'status_code' => 422,
                ], 422);
            }

            DB::beginTransaction();

            $nextId = ((int) DB::table('housing_online_complaint')->max('online_complaint_id')) + 1;
            $formattedId = str_pad((string) $nextId, 2, '0', STR_PAD_LEFT);
            $complaintType = trim($data['complaint_type']) === 'Other'
                ? ucwords((string) ($data['complaint_other_type'] ?? ''))
                : $data['complaint_type'];

            $now = now();
            DB::table('housing_online_complaint')->insert([
                'uid' => $data['uid'],
                'complaint_no' => $now->format('d/m/Y') . '-' . $occupant->flat_no . '-' . $formattedId,
                'complaint_date' => $now->toDateString(),
                'complaint_submission_time' => time(),
                'occupant_flat_id' => $occupant->occupant_flat_id,
                'complaint_type' => $complaintType,
                'complaint_details' => ucfirst(trim($data['complaint_details'])),
                'flat_occupant_id' => $occupant->flat_occupant_id,
                'complaint_status' => 'Submitted',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Thank you. Your complaint has been received.',
                'status_code' => 200,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Complaint create failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit complaint.',
                'status_code' => 500,
            ], 500);
        }
    }

    public function rhewiseList(Request $request)
    {
        $rheName = (int) $request->input('rhe_name', 0);
        if ($rheName === 0) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $rows = DB::table('housing_online_complaint as hoc')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hoc.occupant_flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->where('he.estate_id', $rheName)
            ->orderByDesc('hoc.online_complaint_id')
            ->select('hoc.*', 'hf.flat_no', 'he.estate_name', 'hb.block_name')
            ->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function subdivnRhewiseList(Request $request)
    {
        $subdiv = (int) $request->input('subdiv_id', 0);
        $rheName = (int) $request->input('rhe_name', 0);

        $query = DB::table('housing_online_complaint as hoc')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hoc.occupant_flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_subdivision as hsd', 'hsd.subdiv_id', '=', 'he.subdiv_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->orderByDesc('hoc.online_complaint_id')
            ->select('hoc.*', 'hf.flat_no', 'he.estate_name', 'hb.block_name', 'hsd.subdiv_name');

        if ($subdiv > 0) {
            $query->where('hsd.subdiv_id', $subdiv);
        }
        if ($rheName > 0) {
            $query->where('he.estate_id', $rheName);
        }

        return response()->json(['status' => 'success', 'data' => $query->get()]);
    }

    public function showComplaint(int $id)
    {
        $row = DB::table('housing_online_complaint as hoc')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hoc.occupant_flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->where('hoc.online_complaint_id', $id)
            ->select('hoc.*', 'hf.flat_no', 'he.estate_name')
            ->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Complaint not found.'], 404);
        }

        $actions = DB::table('housing_complaint_action')->where('online_complaint_id', $id)->get();

        return response()->json(['status' => 'success', 'data' => ['complaint' => $row, 'actions' => $actions]]);
    }

    public function storeActionReport(Request $request, int $id)
    {
        $data = $request->validate([
            'uid' => ['required', 'integer'],
            'role_id' => ['required', 'integer'],
            'subdivn_action_report' => ['nullable', 'string', 'max:200'],
            'divn_action_report' => ['nullable', 'string', 'max:200'],
            'action_report_accepted' => ['nullable', 'in:Y,N'],
            'action_report_from_subdivn' => ['nullable', 'string', 'max:200'],
        ]);

        $complaint = DB::table('housing_online_complaint')->where('online_complaint_id', $id)->first();
        if (!$complaint) {
            return response()->json(['status' => 'error', 'message' => 'Complaint not found.'], 404);
        }

        try {
            if ((int) $data['role_id'] === 7) {
                DB::table('housing_complaint_action')->insert([
                    'online_complaint_id' => $id,
                    'subdivn_uid' => $data['uid'],
                    'subdivn_action_report' => ucfirst(trim((string) ($data['subdivn_action_report'] ?? ''))),
                ]);

                return response()->json(['status' => 'success', 'message' => 'Action Report Submitted to Division.']);
            }

            if ((int) $data['role_id'] === 8) {
                $update = [
                    'divn_uid' => $data['uid'],
                    'action_report_accepted' => $data['action_report_accepted'] ?? null,
                ];
                if (($data['action_report_accepted'] ?? '') === 'Y') {
                    $update['action_report_to_occupant'] = $data['action_report_from_subdivn'] ?? '';
                } else {
                    $update['action_report_to_occupant'] = ucfirst(trim((string) ($data['divn_action_report'] ?? '')));
                }

                DB::table('housing_complaint_action')
                    ->where('online_complaint_id', $id)
                    ->update($update);

                DB::table('housing_online_complaint')
                    ->where('online_complaint_id', $id)
                    ->update(['complaint_status' => 'Action Taken']);

                return response()->json(['status' => 'success', 'message' => 'Action Report Submitted.']);
            }

            return response()->json(['status' => 'error', 'message' => 'Unauthorized role for action report.'], 403);
        } catch (\Throwable $e) {
            Log::error('Action report failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Failed to submit action report.'], 500);
        }
    }

    public function complaintHelpers()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'subdivisions' => DB::table('housing_subdivision')->orderBy('subdiv_name')->get(),
                'estates' => DB::table('housing_estate')->orderBy('estate_name')->get(['estate_id', 'estate_name', 'subdiv_id']),
            ],
        ]);
    }

    private function getEligibleOccupantForUser(int $uid): ?object
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
            ->join('housing_flat_occupant as hfo', 'hfo.flat_occupant_id', '=', 'hol.flat_occupant_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->where('hoa.status', 'issued')
            ->where('haod.uid', $uid)
            ->where('hf.flat_status_id', 2)
            ->whereNotNull('hol.uploaded_licence')
            ->select([
                'hfo.flat_id as occupant_flat_id',
                'hfo.flat_occupant_id',
                'hf.flat_no',
            ])
            ->first();
    }
}
