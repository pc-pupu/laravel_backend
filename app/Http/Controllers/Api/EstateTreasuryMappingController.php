<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EstateTreasuryMappingController extends Controller
{
    /**
     * List all estate treasury mappings
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('housing_treasury_estate_mapping as htem')
                ->join('housing_estate as he', 'he.estate_id', '=', 'htem.estate_id')
                ->join('housing_treasury as ht', 'ht.treasury_id', '=', 'htem.treasury_id')
                ->select(
                    'htem.housing_treasury_estate_mapping_id',
                    'htem.estate_id',
                    'htem.treasury_id',
                    'htem.is_active',
                    'he.estate_name',
                    'ht.treasury_name'
                )
                ->orderBy('htem.housing_treasury_estate_mapping_id', 'ASC');

            // Search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(he.estate_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(ht.treasury_name) LIKE ?', ["%{$search}%"]);
                });
            }

            // Active status filter
            if ($request->filled('is_active')) {
                $query->where('htem.is_active', $request->input('is_active'));
            }

            $perPage = $request->input('per_page', 15);
            $mappings = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $mappings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch estate treasury mappings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new estate treasury mapping
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'estate_id' => 'required|integer|exists:housing_estate,estate_id',
            'treasury_id' => 'required|integer|exists:housing_treasury,treasury_id',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check for duplicate mapping
            $existing = DB::table('housing_treasury_estate_mapping')
                ->where('estate_id', $request->estate_id)
                ->where('treasury_id', $request->treasury_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This estate-treasury mapping already exists.',
                ], 422);
            }

            $mappingId = DB::table('housing_treasury_estate_mapping')->insertGetId([
                'estate_id' => $request->estate_id,
                'treasury_id' => $request->treasury_id,
                'is_active' => $request->is_active,
            ], 'housing_treasury_estate_mapping_id');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estate treasury mapping created successfully.',
                'data' => [
                    'housing_treasury_estate_mapping_id' => $mappingId,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create estate treasury mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific estate treasury mapping
     */
    public function show($id)
    {
        try {
            $mapping = DB::table('housing_treasury_estate_mapping as htem')
                ->join('housing_estate as he', 'he.estate_id', '=', 'htem.estate_id')
                ->join('housing_treasury as ht', 'ht.treasury_id', '=', 'htem.treasury_id')
                ->select(
                    'htem.housing_treasury_estate_mapping_id',
                    'htem.estate_id',
                    'htem.treasury_id',
                    'htem.is_active',
                    'he.estate_name',
                    'ht.treasury_name'
                )
                ->where('htem.housing_treasury_estate_mapping_id', $id)
                ->first();

            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estate treasury mapping not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $mapping,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch estate treasury mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an estate treasury mapping
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estate_id' => 'required|integer|exists:housing_estate,estate_id',
            'treasury_id' => 'required|integer|exists:housing_treasury,treasury_id',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $mapping = DB::table('housing_treasury_estate_mapping')
                ->where('housing_treasury_estate_mapping_id', $id)
                ->first();

            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estate treasury mapping not found.',
                ], 404);
            }

            // Check for duplicate mapping (excluding current record)
            $existing = DB::table('housing_treasury_estate_mapping')
                ->where('estate_id', $request->estate_id)
                ->where('treasury_id', $request->treasury_id)
                ->where('housing_treasury_estate_mapping_id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This estate-treasury mapping already exists.',
                ], 422);
            }

            DB::table('housing_treasury_estate_mapping')
                ->where('housing_treasury_estate_mapping_id', $id)
                ->update([
                    'estate_id' => $request->estate_id,
                    'treasury_id' => $request->treasury_id,
                    'is_active' => $request->is_active,
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estate treasury mapping updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update estate treasury mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an estate treasury mapping
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $mapping = DB::table('housing_treasury_estate_mapping')
                ->where('housing_treasury_estate_mapping_id', $id)
                ->first();

            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estate treasury mapping not found.',
                ], 404);
            }

            DB::table('housing_treasury_estate_mapping')
                ->where('housing_treasury_estate_mapping_id', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estate treasury mapping deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete estate treasury mapping: ' . $e->getMessage(),
            ], 500);
        }
    }
}

