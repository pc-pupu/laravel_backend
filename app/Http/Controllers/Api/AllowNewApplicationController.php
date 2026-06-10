<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AllowNewApplicationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string|max:500',
            'id' => 'required',
            'info_type' => 'nullable|string|in:hrms',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $id = $request->input('id');
        $infoType = $request->input('info_type', '');
        $remarks = $request->input('remarks');
        $doneByUid = $request->user()?->uid ?? $request->input('done_by_uid');

        if ($infoType === 'hrms') {
            $application = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('haod.hrms_id', $id)
                ->where('hoa.status', 'existing_occupant')
                ->select([
                    'hoa.online_application_id',
                    'hoa.status',
                    'hoa.application_no',
                    'hoa.computer_serial_no',
                    'hoa.physical_application_no',
                    'haod.hrms_id',
                ])
                ->first();
        } else {
            $application = DB::table('housing_online_application as hoa')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->where('hoa.online_application_id', $id)
                ->select([
                    'hoa.online_application_id',
                    'hoa.status',
                    'hoa.application_no',
                    'hoa.computer_serial_no',
                    'hoa.physical_application_no',
                    'haod.hrms_id',
                ])
                ->first();
        }

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid application. Please try again.',
            ], 404);
        }

        DB::table('housing_allow_new_application_log')->insert([
            'hrms_id' => $application->hrms_id,
            'application_no' => $application->application_no,
            'computer_serial_no' => $application->computer_serial_no,
            'physical_application_no' => $application->physical_application_no,
            'status' => $application->status,
            'online_application_id' => $application->online_application_id,
            'remarks' => $remarks,
            'done_by_uid' => $doneByUid,
            'created_at' => now(),
        ]);

        DB::table('housing_online_application')
            ->where('online_application_id', $application->online_application_id)
            ->update([
                'status' => 'allow_new_application',
                'date_of_verified' => now(),
                'remarks' => $remarks,
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'New application has been allowed successfully.',
        ]);
    }
}
