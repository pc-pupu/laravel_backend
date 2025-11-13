<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\housingCms;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); // or Auth::user()

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // example response
        return response()->json([
            'logged_in' => true,
            'user_id' => $user->id,
            'dashboard_data' => [
                // your dashboard logic here
            ]
        ]);
    }
}
