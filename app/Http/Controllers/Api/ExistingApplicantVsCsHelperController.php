<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExistingApplicantVsCsHelperController extends Controller
{
    /**
     * Get RHE list (specific to user division/subdiv or all for role 13)
     */
    public function getRheList(Request $request)
    {
        $user = auth()->user();
        
        // Check if user has role 13 (admin - can see all RHEs)
        $userRoles = DB::table('user_role')
            ->where('uid', $user->uid)
            ->pluck('rid')
            ->toArray();
        
        $isAdmin = in_array(13, $userRoles);

        $query = DB::table('housing_estate as he')
            ->select('he.estate_id', 'he.estate_name', 'he.estate_address')
            ->orderBy('he.estate_name', 'ASC');

        // If not admin, filter by user's division/subdiv
        if (!$isAdmin) {
            $userDetails = DB::table('users_details')->where('uid', $user->uid)->first();
            
            if ($userDetails && !empty($userDetails->division_id)) {
                if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                    $query->where('he.division_id', $userDetails->division_id)
                        ->where('he.subdiv_id', $userDetails->subdiv_id);
                } else {
                    $query->where('he.division_id', $userDetails->division_id);
                }
            }
        }

        $rheList = $query->get()->mapWithKeys(function ($item) {
            $str = $item->estate_name;
            if (!empty($item->estate_address)) {
                $str = $str . ' | ' . $item->estate_address;
            }
            return [$item->estate_id => $str];
        });

        return response()->json(['status' => 'success', 'data' => $rheList]);
    }

    /**
     * Get flat types under RHE
     */
    public function getFlatTypesUnderRhe(Request $request)
    {
        $rheId = $request->query('rhe_id');
        if (!$rheId) {
            return response()->json(['status' => 'error', 'message' => 'RHE ID is required.'], 400);
        }

        $flatTypes = DB::table('housing_flat as hf')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->where('hf.estate_id', $rheId)
            ->select('hf.flat_type_id', 'hft.flat_type')
            ->groupBy('hf.flat_type_id', 'hft.flat_type')
            ->orderBy('hft.flat_type', 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->flat_type_id => $item->flat_type];
            });

        return response()->json(['status' => 'success', 'data' => $flatTypes]);
    }

    /**
     * Get blocks under RHE and flat type
     */
    public function getBlocksUnderRhe(Request $request)
    {
        $rheId = $request->query('rhe_id');
        $flatTypeId = $request->query('flat_type_id');

        if (!$rheId || !$flatTypeId) {
            return response()->json(['status' => 'error', 'message' => 'RHE ID and Flat Type ID are required.'], 400);
        }

        $blocks = DB::table('housing_flat as hf')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->where('hf.estate_id', $rheId)
            ->where('hf.flat_type_id', $flatTypeId)
            ->select('hf.block_id', 'hb.block_name')
            ->groupBy('hf.block_id', 'hb.block_name')
            ->orderBy('hb.block_name', 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->block_id => $item->block_name];
            });

        return response()->json(['status' => 'success', 'data' => $blocks]);
    }

    /**
     * Get flats under RHE, flat type, and block
     */
    public function getFlatsUnderRhe(Request $request)
    {
        $rheId = $request->query('rhe_id');
        $flatTypeId = $request->query('flat_type_id');
        $blockId = $request->query('block_id');

        
        if (!$rheId || !$flatTypeId || !$blockId) {
            return response()->json(['status' => 'error', 'message' => 'RHE ID, Flat Type ID, and Block ID are required.'], 400);
        }

        $flats = DB::table('housing_flat as hf')
            ->leftJoin('housing_flat_occupant as hfo', 'hf.flat_id', '=', 'hfo.flat_id')
            ->leftJoin('housing_existing_occupant_draft as heod', 'hf.flat_id', '=', 'heod.flat_id')
            ->where('hf.estate_id', $rheId)
            ->where('hf.flat_type_id', $flatTypeId)
            ->where('hf.block_id', $blockId)
            ->select('hf.flat_id', 'hf.flat_no')
            ->orderBy('hf.flat_id', 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->flat_id => $item->flat_no];
            });
            // \Log::info('error log',[$flats]);
        return response()->json(['status' => 'success', 'data' => $flats]);
    }

    /**
     * Get housing estates for current occupancy
     */
    public function getHousingEstates(Request $request)
    {
        $estates = DB::table('housing_estate')
            ->select('estate_id', 'estate_name')
            ->orderBy('estate_name', 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->estate_id => $item->estate_name];
            });

        return response()->json(['status' => 'success', 'data' => $estates]);
    }

    /**
     * Get blocks for housing estate
     */
    public function getHousingBlocks(Request $request)
    {
        $estateId = $request->query('estate_id');
        if (!$estateId) {
            return response()->json(['status' => 'error', 'message' => 'Estate ID is required.'], 400);
        }

        $blocks = DB::table('housing_block as hb')
            ->join('housing_flat as hf', 'hf.block_id', '=', 'hb.block_id')
            ->where('hf.estate_id', $estateId)
            ->select('hb.block_id', 'hb.block_name')
            ->groupBy('hb.block_id', 'hb.block_name')
            ->orderBy('hb.block_name', 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->block_id => $item->block_name];
            });

        return response()->json(['status' => 'success', 'data' => $blocks]);
    }

    /**
     * Get flats for housing estate and block (only occupied flats - status_id = 2)
     */
    public function getHousingFlats(Request $request)
    {
        $estateId = $request->query('estate_id');
        $blockId = $request->query('block_id');

        if (!$estateId || !$blockId) {
            return response()->json(['status' => 'error', 'message' => 'Estate ID and Block ID are required.'], 400);
        }

        $flats = DB::table('housing_flat')
            ->where('estate_id', $estateId)
            ->where('block_id', $blockId)
            ->where('flat_status_id', 2) // Only occupied flats (as per Drupal logic)
            ->select('flat_id', 'flat_no')
            ->orderBy('flat_id', 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->flat_id => $item->flat_no];
            });

        return response()->json(['status' => 'success', 'data' => $flats]);
    }

    /**
     * Get possession date for a flat
     */
    public function getPossessionDate(Request $request)
    {
        $flatId = $request->query('flat_id');
        if (!$flatId) {
            return response()->json(['status' => 'error', 'message' => 'Flat ID is required.'], 400);
        }

        $possessionDate = DB::table('housing_flat_occupant as hfo')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hfo.online_application_id')
            ->where('hfo.flat_id', $flatId)
            ->where('hoa.status', 'existing_occupant')
            ->value('hfo.allotment_date');

        if ($possessionDate) {
            $possessionDate = \Carbon\Carbon::parse($possessionDate)->format('d/m/Y');
        }

        return response()->json(['status' => 'success', 'data' => $possessionDate]);
    }
}

