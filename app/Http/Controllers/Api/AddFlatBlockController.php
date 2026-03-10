<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorLogService;

class AddFlatBlockController extends Controller
{
    public function storeFlatBlock(Request $request)
    {
        $validated = $request->validate([
            'block_name' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            DB::table('housing_block')->insert([
                'block_name' => $validated['block_name'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Flat block created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Add Flat Block Transaction Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            ErrorLogService::logException($e, 'error', ['module' => 'flat_block', 'action' => 'create']);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to create flat block',
            ], 500);
        }
    }
}
