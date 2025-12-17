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

        // Check if user is active
        if ($user->status != 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is inactive. Please contact administrator.'
            ], 403);
        }

        // Handle password migration for Drupal users (if new_pass_set is 0)
        if ($user->new_pass_set == 0) {
            $user->password = Hash::make($request->password);
            $user->new_pass_set = 1;
            $user->save();
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
                'email' => $user->mail,
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

    /**
     * Generate Sanctum Token for SSO Users
     * POST /api/generate-sso-token
     * This is called after SSO token validation to generate a Sanctum token for API access
     */
    public function generateSsoToken(Request $request)
    {
        $request->validate([
            'uid' => 'required|integer',
            'name' => 'required|string',
        ]);

        try {
            $user = User::where('uid', $request->uid)
                ->where('name', $request->name)
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'status_code' => 404
                ], 404);
            }

            // Check if user is active
            if ($user->status != 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User account is inactive',
                    'status_code' => 403
                ], 403);
            }

            // Create Sanctum token
            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Generate SSO Token Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }
}
