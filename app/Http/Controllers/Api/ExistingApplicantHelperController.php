<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;

class ExistingApplicantHelperController extends Controller
{
    /**
     * Get districts list
     */
    public function districts()
    {
        $districts = DB::table('housing_district')
            ->select(DB::raw('trim(district_name) as district_name'), 'district_code')
            ->orderBy('district_name', 'ASC')
            ->get();

        $options = ['' => '- Select -'];
        foreach ($districts as $district) {
            $options[$district->district_code] = $district->district_name;
        }

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Get pay band list based on type (old/new)
     */
    public function payBands(Request $request)
    {
        $type = $request->input('type', 'new'); // 'old' or 'new'

        $query = DB::table('housing_pay_band_categories')
            ->where('flag', $type)
            ->orderBy('pay_band_id', 'ASC');

        $payBands = $query->get();

        $options = [];
        foreach ($payBands as $band) {
            if ($band->scale_from == 0 && $band->scale_to != 0) {
                $str = '(Up to Rs' . $band->scale_to . '/-)';
            } elseif ($band->scale_from != 0 && $band->scale_to != 0) {
                $str = '(Rs.' . $band->scale_from . '/ Up to Rs. ' . $band->scale_to . '/-)';
            } else {
                $str = '(Rs ' . $band->scale_from . '/- and above)';
            }
            $options[$band->pay_band_id] = $str;
        }

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Get RHE flat type based on pay band ID
     */
    public function rheFlatType(Request $request)
    {
        $payBandId = $request->input('pay_band_id');

        if (!$payBandId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pay band ID is required.',
            ], 422);
        }

        $flatType = DB::table('housing_pay_band_categories as hpbc')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hpbc.flat_type_id')
            ->where('hpbc.pay_band_id', $payBandId)
            ->value('hft.flat_type');

        return response()->json([
            'status' => 'success',
            'data' => $flatType ?? '',
        ]);
    }

    /**
     * Get allotment categories based on RHE flat type
     */
    public function allotmentCategories(Request $request)
    {
        $rheFlatType = trim($request->input('rhe_flat_type', ''));

        $options = ['' => '--Choose Allotment Reason--'];

        if (!empty($rheFlatType)) {
            if ($rheFlatType == 'A+') {
                $table = 'housing_roasteraplus_master';
            } elseif ($rheFlatType == 'A' || $rheFlatType == 'B') {
                $table = 'housing_roaster4ab_master';
            } else {
                $table = 'housing_roaster4cd_master';
            }

            $categories = DB::table($table)
                ->select('category')
                ->groupBy('category')
                ->get();

            foreach ($categories as $category) {
                $options[$category->category] = $category->category;
            }
        }

        if (count($options) == 1) {
            $options = ['' => '- Select -'];
        }

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Get DDO designations based on district
     */
    public function ddoDesignations(Request $request)
    {
        $districtCode = $request->input('district_code');
        
        $options = ['' => '- Select -'];

        if ($districtCode) {
            $designations = DB::table('housing_ddo')
                ->where('district_code', $districtCode)
                ->orderBy('ddo_designation', 'ASC')
                ->get();

            foreach ($designations as $ddo) {
                $options[$ddo->ddo_id] = $ddo->ddo_designation;
            }
        }
        Log::info('Fetching DDO designations', ['data' => $options]);
        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Get DDO address based on designation ID
     */
    public function ddoAddress(Request $request)
    {
        $ddoId = $request->input('ddo_id');


        if (!$ddoId) {
            return response()->json([
                'status' => 'success',
                'data' => '',
            ]);
        }
        
        $address = DB::table('housing_ddo')
            ->where('ddo_id', $ddoId)
            ->value('ddo_address');

        // \Log::info('DDO address fetched', ['ddo_id' => $ddoId, 'address' => $address]);

        return response()->json([
            'status' => 'success',
            'data' => $address ?? '',
        ]);
    }


    /**
     * Get flat type ID from flat type name
     */
    public function flatTypeId(Request $request)
    {
        $flatType = trim($request->input('flat_type'));

        if (!$flatType) {
            return response()->json([
                'status' => 'error',
                'message' => 'Flat type is required.',
            ], 422);
        }

        $flatTypeId = DB::table('housing_flat_type')
            ->where('flat_type', $flatType)
            ->value('flat_type_id');

        return response()->json([
            'status' => 'success',
            'data' => $flatTypeId ?? 0,
        ]);
    }
}

