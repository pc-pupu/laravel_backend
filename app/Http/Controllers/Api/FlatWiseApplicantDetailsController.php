<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlatWiseApplicantDetailsController extends Controller
{
    public function list(Request $request)
    {
        $rheName = (int) $request->input('rhe_name', 0);
        $flatType = (int) $request->input('flat_type', 0);
        $blockName = (int) $request->input('block_name', 0);

        if ($rheName === 0 || $flatType === 0 || $blockName === 0) {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'Select RHE, flat type, and block to view applicants.',
            ]);
        }

        $draft = DB::table('housing_existing_occupant_draft as heod')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'heod.flat_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->where('hf.estate_id', $rheName)
            ->where('hf.flat_type_id', $flatType)
            ->where('hf.block_id', $blockName)
            ->select('heod.applicant_name')
            ->orderBy('hb.block_name')
            ->orderBy('hf.flat_no')
            ->pluck('applicant_name');

        $occupants = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->whereIn('hoa.status', ['existing_occupant', 'flat_possession_taken'])
            ->where('hf.estate_id', $rheName)
            ->where('hf.flat_type_id', $flatType)
            ->where('hf.block_id', $blockName)
            ->orderBy('hf.flat_no')
            ->pluck('ha.applicant_name');

        $names = $draft->merge($occupants)->values()->map(fn ($name, $i) => [
            'serial_no' => $i + 1,
            'applicant_name' => $name,
        ]);

        return response()->json(['status' => 'success', 'data' => $names]);
    }

    public function helpers(Request $request)
    {
        $estates = DB::table('housing_estate')->orderBy('estate_name')->get(['estate_id', 'estate_name']);
        $flatTypes = DB::table('housing_flat_type')->orderBy('flat_type_id')->get(['flat_type_id', 'flat_type']);

        $blocks = [];
        if ($request->filled('rhe_name') && $request->filled('flat_type')) {
            $blocks = DB::table('housing_block as hb')
                ->join('housing_flat as hf', 'hf.block_id', '=', 'hb.block_id')
                ->where('hf.estate_id', (int) $request->rhe_name)
                ->where('hf.flat_type_id', (int) $request->flat_type)
                ->distinct()
                ->orderBy('hb.block_name')
                ->get(['hb.block_id', 'hb.block_name']);
        }

        return response()->json([
            'status' => 'success',
            'data' => compact('estates', 'flatTypes', 'blocks'),
        ]);
    }
}
