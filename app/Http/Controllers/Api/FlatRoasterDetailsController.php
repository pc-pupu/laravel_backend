<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlatRoasterDetailsController extends Controller
{
    public function index(Request $request)
    {
        $flatTypeId = (int) $request->input('flat_type_id', 0);

        $flatTypes = DB::table('housing_flat_type')
            ->orderBy('flat_type_id')
            ->pluck('flat_type', 'flat_type_id');

        if ($flatTypeId === 0) {
            return response()->json([
                'status' => 'success',
                'data' => ['flat_types' => $flatTypes, 'rows' => []],
            ]);
        }

        $vacancyCount = DB::table('housing_flat')
            ->where('flat_type_id', $flatTypeId)
            ->where('flat_status_id', 1)
            ->whereIn('floor', ['Ground', 'Top'])
            ->count();

        $counter = DB::table('housing_allotment_roaster_counter')
            ->where('allotment_type', $flatTypeId)
            ->orderByDesc('id')
            ->value('last_roaster_counter');

        return response()->json([
            'status' => 'success',
            'data' => [
                'flat_types' => $flatTypes,
                'vacancy_count' => $vacancyCount,
                'last_roaster_counter' => $counter ?? 0,
                'flat_type_id' => $flatTypeId,
            ],
        ]);
    }
}
