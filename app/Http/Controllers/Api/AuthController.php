<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RsaEncryptionService;
use DB;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\ErrorLogService;

class AuthController extends Controller
{
    /**
     * Audit: Expose RSA public key for client-side password encryption.
     */
    public function getPublicKey(RsaEncryptionService $rsa): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'public_key' => $rsa->getPublicKey(),
        ]);
    }

    /**
     * Login API. Audit: Accepts password_encrypted (RSA) - decrypted server-side before verification.
     */
    public function login(Request $request, RsaEncryptionService $rsa)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required_without:password_encrypted|nullable|string|max:255',
            'password_encrypted' => 'nullable|string|max:4096',
        ]);

        $password = null;
        if ($request->filled('password_encrypted')) {
            try {
                $password = $rsa->decrypt($request->password_encrypted);
            } catch (\Exception $e) {
                ErrorLogService::logException($e, 'warning', ['module' => 'auth', 'action' => 'login_decrypt']);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }
        } else {
            $password = $request->password;
        }

        if (!$password || strlen($password) > 255) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('name', $request->name)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Username not found'
            ], 404);
        }

        if ($user->status != 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is inactive. Please contact administrator.'
            ], 403);
        }

        if ($user->new_pass_set == 0) {
            $user->password = Hash::make($password);
            $user->new_pass_set = 1;
            $user->save();
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid password'
            ], 401);
        }

        $token = $user->createToken('api_token')->plainTextToken;
        $user_role = DB::table('user_role')
            ->where('uid', $user->uid)
            ->select('rid')->first();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => [
                'uid' => $user->uid,
                'role' => $user_role->rid,
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
            ErrorLogService::logException($e, 'error', ['module' => 'auth', 'action' => 'generate_sso_token']);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }
}
