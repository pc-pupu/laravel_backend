<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Helpers\AuthEncryptionHelper;
use Carbon\Carbon;

class AuthApiServiceController extends Controller
{
    /**
     * HRMS Applicant Login API
     * POST /api/login-hrms
     */
    public function applicantLogin(Request $request)
    {
        if ($request->method() !== 'POST') {
            return response()->json([
                'status' => 'error',
                'message' => 'Method not allowed',
                'status_code' => 405
            ], 405);
        }

        // Check referer
        $referer = $request->header('Referer');
        $allowedReferer = config('services.hrms.uat_hrms_url', '');
        
        if (empty($referer) || $referer !== $allowedReferer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: Invalid Origin or Missing Origin',
                'status_code' => 400
            ], 400);
        }

        try {
            $encdata = $request->input('encdata');
            $cs = $request->input('cs');

            if (empty($encdata) || empty($cs)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing required data',
                    'status_code' => 400
                ], 400);
            }

            // Decrypt the data
            $decrypted_data = AuthEncryptionHelper::decrypt($encdata);
            $dataObj = json_decode($decrypted_data);

            if (!$dataObj) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid encrypted data',
                    'status_code' => 400
                ], 400);
            }

            // Validate timestamp (prevent replay attack)
            if (isset($dataObj->sysTimeStamp)) {
                $reqTime = \DateTime::createFromFormat("d/m/Y H:i:s", $dataObj->sysTimeStamp);
                
                if ($reqTime === false) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid timestamp format',
                        'status_code' => 400
                    ], 400);
                }

                $reqUnixTime = $reqTime->getTimestamp();
                $currentUnixTime = time();

                if (abs($currentUnixTime - $reqUnixTime) > 120) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Expired Request',
                        'status_code' => 400
                    ], 400);
                }
            }

            // Set timeout
            set_time_limit(10);
            $requestStartTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));
            if (microtime(true) - $requestStartTime > 10) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request timed out. Please try again.',
                    'status_code' => 408
                ], 408);
            }

            // Log the request
            $hrms_json_log = [
                'hrms_id' => $dataObj->hrmsid ?? null,
                'json_encrypted_data' => $encdata,
                'json_decrypted_data' => $decrypted_data,
                'created_at' => now()
            ];
            DB::table('housing_hrms_applicant_login_log')->insert($hrms_json_log);

            // Validate checksum
            $cksm = AuthEncryptionHelper::checksumValidation($decrypted_data);
            if ($cs !== $cksm) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Checksum mismatch',
                    'status_code' => 400
                ], 400);
            }

            if (empty($dataObj->hrmsid)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing required data',
                    'status_code' => 400
                ], 400);
            }

            // Check if user exists, create if not
            $account = DB::table('users')->where('name', trim($dataObj->hrmsid))->first();
            
            if (empty($account)) {
                $mail = isset($dataObj->email) ? trim($dataObj->email) : trim($dataObj->hrmsid) . '@gmail.com';
                $loginName = trim($dataObj->hrmsid);
                
                $userData = [
                    'name' => $loginName,
                    'password' => Hash::make($loginName),
                    'password_old' => Hash::make($loginName),
                    'mail' => $mail,
                    'status' => 1,
                    'new_pass_set' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $uid = DB::table('users')->insertGetId($userData, 'uid');
                
                // Assign Applicant role (role ID 4 in Drupal)
                DB::table('user_role')->insert([
                    'uid' => $uid,
                    'rid' => 4, // Applicant role
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Generate SSO token
            $hrmscode = \App\Helpers\UrlEncryptionHelper::encryptRaw($dataObj->hrmsid);
            $timestamp = time();
            $message = $hrmscode . "|" . $timestamp;
            $hmacSecret = config('services.hrms.hmac_secret_me', '');
            $hmac = hash_hmac("sha256", $message, $hmacSecret);
            $token = base64_encode($message . "|" . $hmac);
            
            $baseUrl = $request->getSchemeAndHttpHost() . $request->getBasePath();
            $redirect_url = $baseUrl . '/user/sso/' . urlencode($token);

            return response()->json([
                'status' => 'success',
                'redirect_url' => $redirect_url,
                'message' => 'Redirection URL sent',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('HRMS Applicant Login Error', [
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

    /**
     * DDO Login API
     * POST /api/login-ddo
     */
    public function ddoLogin(Request $request)
    {
        if ($request->method() !== 'POST') {
            return response()->json([
                'status' => 'error',
                'message' => 'Method not allowed',
                'status_code' => 405
            ], 405);
        }

        // Check referer
        $referer = $request->header('Referer');
        $allowedReferer = config('services.hrms.uat_hrms_url', '');
        
        if (empty($referer) || $referer !== $allowedReferer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: Invalid Origin or Missing Origin',
                'status_code' => 400
            ], 400);
        }

        try {
            $encdata = $request->input('encdata');
            $cs = $request->input('cs');

            if (empty($encdata) || empty($cs)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing required data',
                    'status_code' => 400
                ], 400);
            }

            // Decrypt the data
            $decrypted_data = AuthEncryptionHelper::decrypt($encdata);
            $dataObj = json_decode($decrypted_data);

            if (!$dataObj) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid encrypted data',
                    'status_code' => 400
                ], 400);
            }

            // Validate timestamp (prevent replay attack)
            if (isset($dataObj->sysTimeStamp)) {
                $reqTime = \DateTime::createFromFormat("d/m/Y H:i:s", $dataObj->sysTimeStamp);
                
                if ($reqTime === false) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid timestamp format',
                        'status_code' => 400
                    ], 400);
                }

                $reqUnixTime = $reqTime->getTimestamp();
                $currentUnixTime = time();

                if (abs($currentUnixTime - $reqUnixTime) > 120) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Expired Request',
                        'status_code' => 400
                    ], 400);
                }
            }

            // Set timeout
            set_time_limit(10);
            $requestStartTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));
            if (microtime(true) - $requestStartTime > 10) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request timed out. Please try again.',
                    'status_code' => 408
                ], 408);
            }

            // Log the request
            $hrms_json_log = [
                'hrms_id' => $dataObj->hrmsid ?? null,
                'ddo_code' => $dataObj->ddo_code ?? null,
                'json_encrypted_data' => $encdata,
                'json_decrypted_data' => $decrypted_data,
                'created_at' => now()
            ];
            DB::table('housing_hrms_ddo_login_log')->insert($hrms_json_log);

            // Validate checksum
            $cksm = AuthEncryptionHelper::checksumValidation($decrypted_data);
            if ($cs !== $cksm) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Checksum mismatch',
                    'status_code' => 400
                ], 400);
            }

            if (empty($dataObj->ddo_code)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing required data',
                    'status_code' => 400
                ], 400);
            }

            // Check DDO-HRMS mapping
            $mapping = DB::table('housing_ddo_hrms_mapping')
                ->where('ddo_code', $dataObj->ddo_code)
                ->where('hrms_id', $dataObj->hrmsid)
                ->where('is_active', 'Y')
                ->first();

            if (empty($mapping)) {
                // Update previous entries to inactive
                DB::table('housing_ddo_hrms_mapping')
                    ->where('ddo_code', $dataObj->ddo_code)
                    ->update(['is_active' => 'N']);

                // Insert new mapping
                DB::table('housing_ddo_hrms_mapping')->insert([
                    'ddo_code' => $dataObj->ddo_code,
                    'hrms_id' => $dataObj->hrmsid,
                    'created_datetime' => now(),
                    'is_active' => 'Y'
                ]);

                // Create user if not exists
                $account = DB::table('users')->where('name', trim($dataObj->ddo_code))->first();
                
                if (empty($account)) {
                    $userData = [
                        'name' => trim($dataObj->ddo_code),
                        'password' => Hash::make(trim($dataObj->ddo_code)),
                        'password_old' => Hash::make(trim($dataObj->ddo_code)),
                        'mail' => $dataObj->email ?? trim($dataObj->ddo_code) . '@gmail.com',
                        'status' => 1,
                        'new_pass_set' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $uid = DB::table('users')->insertGetId($userData, 'uid');
                    
                    // Assign DDO role (role ID 11 in Drupal)
                    DB::table('user_role')->insert([
                        'uid' => $uid,
                        'rid' => 11, // DDO role
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Generate SSO token
            $ddocode = \App\Helpers\UrlEncryptionHelper::encryptRaw($dataObj->ddo_code);
            $timestamp = time();
            $message = $ddocode . "|" . $timestamp;
            $hmacSecret = config('services.hrms.hmac_secret_me', '');
            $hmac = hash_hmac("sha256", $message, $hmacSecret);
            $token = base64_encode($message . "|" . $hmac);
            
            $baseUrl = $request->getSchemeAndHttpHost() . $request->getBasePath();
            $redirect_url = $baseUrl . '/sso/ddo/' . urlencode($token);

            return response()->json([
                'status' => 'success',
                'redirect_url' => $redirect_url,
                'message' => 'Redirection URL sent',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('DDO Login Error', [
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

    /**
     * Validate HRMS SSO Token and Return User Data
     * POST /api/validate-sso-token
     */
    public function validateSsoToken(Request $request)
    {
        // \Log::info('SSO Token Validation1', [
        //         'token' => $request->input('token'),
        //         'token_length' => strlen($request->input('token') ?? '')
        //     ]);
        try {
            $token = $request->input('token');
            $maxAge = $request->input('max_age', 120);

            if (empty($token)) {
                Log::error('SSO Token Validation: No token provided');
                return response()->json([
                    'status' => 'error',
                    'message' => 'No SSO token provided',
                    'status_code' => 400
                ], 400);
            }

            // Log::info('SSO Token Validation', [
            //     'token' => $token,
            //     'token_length' => strlen($token)
            // ]);

            $decoded = base64_decode($token);
            
            // Log::info('Token decoded', [
            //     'decoded' => $decoded,
            //     'pipe_count' => substr_count($decoded, '|')
            // ]);

            if (!$decoded || substr_count($decoded, '|') !== 2) {
                Log::error('SSO Token Validation: Invalid format', [
                    'decoded' => $decoded,
                    'pipe_count' => substr_count($decoded, '|')
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token format',
                    'status_code' => 400
                ], 400);
            }

            list($code, $timestamp, $receivedHmac) = explode("|", $decoded);

            // Compute expected HMAC
            $hmacSecret = config('services.hrms.hmac_secret_me', '1Po/Pt8oRnNzy9QZ7NZJjA==');
            $expectedHmac = hash_hmac("sha256", $code . "|" . $timestamp, $hmacSecret);

            if (!hash_equals($expectedHmac, $receivedHmac)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Token',
                    'status_code' => 400
                ], 400);
            }

            // Check timestamp validity
            if (abs(time() - (int)$timestamp) > $maxAge) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request Token Expired',
                    'status_code' => 400
                ], 400);
            }

            // Decrypt the code
            $decryptedCode = \App\Helpers\UrlEncryptionHelper::decryptRaw($code);

            // Load user by name
            $account = DB::table('users')->where('name', $decryptedCode)->first();

            if (!$account || !$account->uid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Token and User or User Not Found',
                    'status_code' => 400
                ], 400);
            }

            // Update last updated time
            DB::table('users')
                ->where('uid', $account->uid)
                ->update(['updated_at' => now()]);

            // Log the login
            // Log::info('Session opened via SSO', ['name' => $account->name]);

            return response()->json([
                'status' => 'success',
                'user' => [
                    'uid' => $account->uid,
                    'name' => $account->name,
                    'email' => $account->mail ?? null,
                ],
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('SSO Token Validation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Token',
                'status_code' => 400
            ], 400);
        }
    }

    /**
     * Manual HRMS Login (from form)
     * POST /api/hrms-login-manual
     */
    public function hrmsLoginManual(Request $request)
    {
        try {
            $hrmsId = $request->input('hrms_id');
            
            if (empty($hrmsId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'HRMS ID is required',
                    'status_code' => 400
                ], 400);
            }

            $hrmsId = trim($hrmsId);

            // Check if user exists, create if not
            $account = DB::table('users')->where('name', $hrmsId)->first();
            //    Log::info('Manual HRMS Login: Checking user', [
            //        'hrms_id' => $hrmsId,
            //        'account_exists' => $account ? true : false
            //    ]);
            if (empty($account)) {
                $mail = $hrmsId . '@gmail.com';
                
                $userData = [
                    'name' => $hrmsId,
                    'password' => Hash::make($hrmsId),
                    'password_old' => Hash::make($hrmsId),
                    'mail' => $mail,
                    'status' => 1,
                    'new_pass_set' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $uid = DB::table('users')->insertGetId($userData, 'uid');
                
                // Assign Applicant role (role ID 4)
                DB::table('user_role')->insert([
                    'uid' => $uid,
                    'rid' => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Generate SSO token
            $hrmscode = \App\Helpers\UrlEncryptionHelper::encryptRaw($hrmsId);
            $timestamp = time();
            $message = $hrmscode . "|" . $timestamp;
            $hmacSecret = config('services.hrms.hmac_secret_me', '1Po/Pt8oRnNzy9QZ7NZJjA==');
            $hmac = hash_hmac("sha256", $message, $hmacSecret);
            $token = base64_encode($message . "|" . $hmac);
            
            return response()->json([
                'status' => 'success',
                'token' => $token,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Manual HRMS Login Error', [
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

