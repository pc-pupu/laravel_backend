<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutoCancellationListController extends Controller
{
    public function index(Request $request)
    {
        $uid = (int) $request->input('uid');
        $userDetails = DB::table('users_details')->where('uid', $uid)->first();

        $query = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->whereIn('hoa.status', ['offer_letter_cancel', 'license_cancel', 'offer_letter_extended', 'license_extended']);

        if ($userDetails && !empty($userDetails->division_id)) {
            $query->where('he.division_id', $userDetails->division_id);
            if (!empty($userDetails->subdiv_id) && (int) $userDetails->subdiv_id !== 0) {
                $query->where('he.subdiv_id', $userDetails->subdiv_id);
            }
        }

        $rows = $query->select([
            'hoa.application_no',
            'ha.applicant_name',
            'haod.applicant_designation',
            'hoa.status',
            'hf.floor',
            'hf.flat_no',
            'hb.block_name',
            'he.estate_name',
        ])->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }
}
