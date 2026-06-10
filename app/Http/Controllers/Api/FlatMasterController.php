<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlatMasterController extends Controller
{
    public function meta()
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'rhes' => DB::table('housing_estate')->select('estate_id', 'estate_name', 'estate_address')->orderBy('estate_name')->get(),
                    'flat_types' => DB::table('housing_flat_type')->select('flat_type_id', 'flat_type')->orderBy('flat_type')->get(),
                    'blocks' => DB::table('housing_block')->select('block_id', 'block_name')->orderBy('block_name')->get(),
                    'statuses' => DB::table('housing_flat_status')->select('flat_status_id', 'flat_status')->orderBy('flat_status_id')->get(),
                ],
                'status_code' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('Flat master meta failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load master data.',
                'status_code' => 500,
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = DB::table('housing_flat as hf')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->leftJoin('housing_flat_status as hfs', 'hfs.flat_status_id', '=', 'hf.flat_status_id')
            ->select([
                'hf.flat_id',
                'hf.flat_no',
                'hf.floor',
                'hf.estate_id',
                'hf.flat_type_id',
                'hf.block_id',
                'hf.flat_status_id',
                'hf.remarks',
                'he.estate_name',
                'hft.flat_type',
                'hb.block_name',
                'hfs.flat_status',
            ]);

        foreach (['estate_id', 'flat_type_id', 'block_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where('hf.' . $filter, $request->integer($filter));
            }
        }

        $rows = $query->orderBy('hf.flat_id')->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'status_code' => 200,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'estate_id' => ['required', 'integer'],
            'flat_type_id' => ['required', 'integer'],
            'block_id' => ['required', 'integer'],
            'floor' => ['required', 'string', 'max:50'],
            'flat_no' => ['required', 'string', 'max:50'],
            'flat_status_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicate = DB::table('housing_flat')
            ->where('estate_id', $data['estate_id'])
            ->where('flat_type_id', $data['flat_type_id'])
            ->where('block_id', $data['block_id'])
            ->where('floor', $data['floor'])
            ->whereRaw('UPPER(flat_no) = ?', [strtoupper(trim($data['flat_no']))])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'This flat already exists for selected RHE/type/block/floor.',
                'status_code' => 422,
            ], 422);
        }

        DB::table('housing_flat')->insert([
            'estate_id' => $data['estate_id'],
            'flat_type_id' => $data['flat_type_id'],
            'block_id' => $data['block_id'],
            'floor' => $data['floor'],
            'flat_no' => strtoupper(trim($data['flat_no'])),
            'flat_status_id' => $data['flat_status_id'],
            'remarks' => $data['remarks'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Flat added successfully.',
            'status_code' => 201,
        ], 201);
    }

    public function show(int $flatId)
    {
        $row = DB::table('housing_flat')->where('flat_id', $flatId)->first();
        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Flat not found.', 'status_code' => 404], 404);
        }
        return response()->json(['status' => 'success', 'data' => $row, 'status_code' => 200]);
    }

    public function update(Request $request, int $flatId)
    {
        $data = $request->validate([
            'estate_id' => ['required', 'integer'],
            'flat_type_id' => ['required', 'integer'],
            'block_id' => ['required', 'integer'],
            'floor' => ['required', 'string', 'max:50'],
            'flat_no' => ['required', 'string', 'max:50'],
            'flat_status_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $exists = DB::table('housing_flat')->where('flat_id', $flatId)->exists();
        if (!$exists) {
            return response()->json(['status' => 'error', 'message' => 'Flat not found.', 'status_code' => 404], 404);
        }

        $duplicate = DB::table('housing_flat')
            ->where('flat_id', '!=', $flatId)
            ->where('estate_id', $data['estate_id'])
            ->where('flat_type_id', $data['flat_type_id'])
            ->where('block_id', $data['block_id'])
            ->where('floor', $data['floor'])
            ->whereRaw('UPPER(flat_no) = ?', [strtoupper(trim($data['flat_no']))])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Another flat with same identifiers already exists.',
                'status_code' => 422,
            ], 422);
        }

        DB::table('housing_flat')
            ->where('flat_id', $flatId)
            ->update([
                'estate_id' => $data['estate_id'],
                'flat_type_id' => $data['flat_type_id'],
                'block_id' => $data['block_id'],
                'floor' => $data['floor'],
                'flat_no' => strtoupper(trim($data['flat_no'])),
                'flat_status_id' => $data['flat_status_id'],
                'remarks' => $data['remarks'] ?? null,
            ]);

        return response()->json(['status' => 'success', 'message' => 'Flat updated successfully.', 'status_code' => 200]);
    }

    public function destroy(int $flatId)
    {
        $deleted = DB::table('housing_flat')->where('flat_id', $flatId)->delete();
        if (!$deleted) {
            return response()->json(['status' => 'error', 'message' => 'Flat not found.', 'status_code' => 404], 404);
        }
        return response()->json(['status' => 'success', 'message' => 'Flat deleted successfully.', 'status_code' => 200]);
    }
}
