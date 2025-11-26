<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstateTreasuryMappingHelperController extends Controller
{
    /**
     * Get list of all estates
     */
    public function getEstates(Request $request)
    {
        try {
            $estateId = $request->input('estate_id');

            if ($estateId) {
                // Return single estate
                $estate = DB::table('housing_estate')
                    ->where('estate_id', $estateId)
                    ->select('estate_id', 'estate_name')
                    ->first();

                if (!$estate) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Estate not found.',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $estate,
                ]);
            } else {
                // Return all estates
                $estates = DB::table('housing_estate')
                    ->select('estate_id', 'estate_name')
                    ->orderBy('estate_name', 'ASC')
                    ->get();

                $options = [];
                foreach ($estates as $estate) {
                    $options[$estate->estate_id] = $estate->estate_name;
                }

                return response()->json([
                    'success' => true,
                    'data' => $options,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch estates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of all treasuries
     */
    public function getTreasuries(Request $request)
    {
        try {
            $treasuryId = $request->input('treasury_id');

            if ($treasuryId) {
                // Return single treasury
                $treasury = DB::table('housing_treasury')
                    ->where('treasury_id', $treasuryId)
                    ->select('treasury_id', 'treasury_name')
                    ->first();

                if (!$treasury) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Treasury not found.',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $treasury,
                ]);
            } else {
                // Return all treasuries
                $treasuries = DB::table('housing_treasury')
                    ->select('treasury_id', 'treasury_name')
                    ->orderBy('treasury_name', 'ASC')
                    ->get();

                $options = [];
                foreach ($treasuries as $treasury) {
                    $options[$treasury->treasury_id] = $treasury->treasury_name;
                }

                return response()->json([
                    'success' => true,
                    'data' => $options,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treasuries: ' . $e->getMessage(),
            ], 500);
        }
    }
}

