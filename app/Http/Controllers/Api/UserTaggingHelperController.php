<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserTaggingHelperController extends Controller
{
    /**
     * Get RHE List
     * GET /api/user-tagging/helpers/rhe-list
     */
    public function getRheList()
    {
        try {
            $rheList = DB::table('housing_estate')
                ->orderBy('estate_name', 'ASC')
                ->get(['estate_id', 'estate_name', 'estate_address'])
                ->map(function($item) {
                    $label = $item->estate_name;
                    if (!empty($item->estate_address)) {
                        $label .= ' | ' . $item->estate_address;
                    }
                    return [
                        'estate_id' => $item->estate_id,
                        'label' => $label
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $rheList,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('RHE List Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get Flat Types under RHE
     * GET /api/user-tagging/helpers/flat-types/{rhe_id}
     */
    public function getFlatTypes($rheId)
    {
        try {
            if (empty($rheId) || !is_numeric($rheId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid RHE ID',
                    'status_code' => 400
                ], 400);
            }

            $flatTypes = DB::table('housing_flat as hf')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                ->where('hf.estate_id', $rheId)
                ->select('hft.flat_type_id', 'hft.flat_type')
                ->distinct()
                ->orderBy('hft.flat_type_id', 'ASC')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $flatTypes,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Flat Types Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get Blocks under RHE and Flat Type
     * GET /api/user-tagging/helpers/blocks/{rhe_id}/{flat_type_id}
     */
    public function getBlocks($rheId, $flatTypeId)
    {
        try {
            if (empty($rheId) || !is_numeric($rheId) || empty($flatTypeId) || !is_numeric($flatTypeId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid parameters',
                    'status_code' => 400
                ], 400);
            }

            $blocks = DB::table('housing_flat as hf')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->where('hf.estate_id', $rheId)
                ->where('hf.flat_type_id', $flatTypeId)
                ->select('hb.block_id', 'hb.block_name')
                ->distinct()
                ->orderBy('hb.block_id', 'ASC')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $blocks,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Blocks Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get Floor Numbers under RHE, Flat Type, and Block
     * GET /api/user-tagging/helpers/floors/{rhe_id}/{flat_type_id}/{block_id}
     */
    public function getFloors($rheId, $flatTypeId, $blockId)
    {
        try {
            if (empty($rheId) || !is_numeric($rheId) || 
                empty($flatTypeId) || !is_numeric($flatTypeId) ||
                empty($blockId) || !is_numeric($blockId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid parameters',
                    'status_code' => 400
                ], 400);
            }

            $floors = DB::table('housing_flat')
                ->where('estate_id', $rheId)
                ->where('flat_type_id', $flatTypeId)
                ->where('block_id', $blockId)
                ->select('floor','flat_id', 'estate_id', 'flat_type_id', 'block_id')
                // ->distinct()
                ->orderBy('floor', 'ASC')
                ->get()
                ->pluck('floor')
                ->unique();

            return response()->json([
                'status' => 'success',
                'data' => $floors->values(),
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Floors Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get Flat Numbers under RHE, Flat Type, Block, and Floor
     * GET /api/user-tagging/helpers/flats/{rhe_id}/{flat_type_id}/{block_id}/{floor}
     */
    public function getFlats(Request $request, $rheId, $flatTypeId, $blockId, $floor)
    {
        try {
            if (empty($rheId) || !is_numeric($rheId) || 
                empty($flatTypeId) || !is_numeric($flatTypeId) ||
                empty($blockId) || !is_numeric($blockId) ||
                empty($floor)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid parameters',
                    'status_code' => 400
                ], 400);
            }

            $query = DB::table('housing_flat')
                ->where('estate_id', $rheId)
                ->where('flat_type_id', $flatTypeId)
                ->where('block_id', $blockId)
                ->where('floor', $floor)
                ->select('flat_id', 'flat_no');

            // Optional: Filter by flat status (only vacant flats)
            $flatStatus = $request->query('flat_status', null);
            if ($flatStatus == 1) {
                $query->where('flat_status_id', 1);
            }

            $flats = $query->orderBy('flat_id', 'ASC')->get();

            return response()->json([
                'status' => 'success',
                'data' => $flats,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Flats Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }
}

