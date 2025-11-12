<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Login API
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
        ]);

        // Check if user exists
        $user = User::where('name', $request->name)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Username not found'
            ], 404);
        }

        if ($user) {
            if($user->new_pass_set == 0) {
                $user->password = Hash::make($request->password);
                $user->new_pass_set = 1;
                $user->save();

            }
        }

        // Password check 
        $isPasswordCorrect = Hash::check($request->password, $user->password);

        if (!$isPasswordCorrect) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid password'
            ], 401);
        }

        
        // ✅ Create token using the user model directly
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => [
                'uid' => $user->uid,
                'name' => $user->name,
            ],
            'token' => $token,
        ]);

    }

    // Get Authenticated User
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    // Logout API
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
