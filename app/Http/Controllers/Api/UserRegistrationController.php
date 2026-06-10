<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRegistrationRequest;
use App\Services\ErrorLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserRegistrationController extends Controller
{
    public function show(int $uid)
    {
        $registration = DB::table('users as u')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'u.uid')
            ->join('housing_applicant_official_detail as haod', 'haod.uid', '=', 'u.uid')
            ->where('u.uid', $uid)
            ->select([
                'u.uid',
                'u.name as username',
                'u.mail as email',
                'u.created_at',
                'ha.applicant_name',
                'ha.gender',
                'ha.mobile_no',
                'ha.date_of_birth',
                'haod.hrms_id',
                'haod.applicant_designation',
                'haod.office_name',
            ])
            ->first();

        if (!$registration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $registration,
        ]);
    }

    /**
     * Register a new applicant.
     * 
     * This creates:
     * 1. A Drupal user account
     * 2. Housing applicant profile
     * 3. Official employment details
     * 4. Assigns 'Applicant' role
     */
    public function register(UserRegistrationRequest $request)
    {
        try {
            DB::beginTransaction();

            $applicantName = strtoupper(trim($request->applicant_name));
            $designation = strtoupper(trim($request->app_designation));
            $officeName = strtoupper(trim($request->office_name));

            // Step 1: Generate unique username using Drupal's word-count algorithm
            $username = $this->generateUniqueUsername($applicantName);

            // Step 2: Create Drupal user account
            $uid = DB::table('users')->insertGetId([
                'name' => $username,
                'mail' => strtolower($request->email),
                'password' => Hash::make($username),
                'password_old' => Hash::make($username),
                'status' => 0,
                'new_pass_set' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ], 'uid');

            // Step 3: Assign 'Applicant' role (uid 4)
            DB::table('user_role')->insert([
                'uid' => $uid,
                'rid' => 4, // Applicant role ID
            ]);

            // Step 4: Create housing applicant profile
            $housingApplicantId = DB::table('housing_applicant')->insertGetId([
                'uid' => $uid,
                'applicant_name' => $applicantName,
                'date_of_birth' => $this->convertDateFormat($request->dob),
                'gender' => trim($request->gender),
                'mobile_no' => trim($request->mobile),
            ], 'housing_applicant_id');

            // Step 5: Create official details
            DB::table('housing_applicant_official_detail')->insert([
                'uid' => $uid,
                'housing_applicant_id' => $housingApplicantId,
                'hrms_id' => trim($request->hrms_id),
                'applicant_designation' => $designation,
                'office_name' => $officeName,
            ]);

            DB::commit();

            // Step 6: Send confirmation email with login details
            $this->sendRegistrationEmail(strtolower($request->email), $username);

            return response()->json([
                'status' => 'success',
                'message' => 'Registration submitted successfully. Your account will be activated after verification by the Housing Department.',
                'data' => [
                    'uid' => $uid,
                    'username' => $username,
                    'email' => $request->email,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            ErrorLogService::logException($e, 'error', ['module' => 'user_registration', 'action' => 'register']);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Generate unique username using word-count algorithm.
     * Example: "John Smith" => "johsmi45234"
     * 
     * Algorithm:
     * Drupal behavior:
     * - Ignore Dr/Dr. prefix.
     * - Take first 3 chars from one or two name parts.
     * - Remove dots and append a random number.
     */
    private function generateUniqueUsername(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $baseParts = $parts;

        if (!empty($baseParts) && in_array(strtolower(rtrim($baseParts[0], '.')), ['dr'], true)) {
            array_shift($baseParts);
        }

        $base = strtolower(substr($baseParts[0] ?? 'usr', 0, 3));
        if (count($baseParts) > 1) {
            $base .= strtolower(substr($baseParts[1], 0, 3));
        }

        $base = str_replace('.', '', $base);
        do {
            $username = $base . random_int(1, 100000);
        } while (DB::table('users')->where('name', $username)->exists());

        return $username;
    }

    private function sendRegistrationEmail(string $email, string $username): void
    {
        try {
            $body = "Dear Applicant,\n\n"
                . "You have successfully registered. Please find below your login details. Please change your password after login.\n\n"
                . "Username - {$username}\n"
                . "Password - {$username}\n\n"
                . "Please login using your username and password to apply.\n\n"
                . "Regards,\nHousing Department\nGovernment of West Bengal";

            Mail::raw($body, function ($message) use ($email) {
                $message->to($email)->subject('Applicant Login Details');
            });
        } catch (\Throwable $e) {
            ErrorLogService::logException($e, 'warning', [
                'module' => 'user_registration',
                'action' => 'registration_email',
            ]);
        }
    }

    /**
     * Convert date from DD/MM/YYYY to YYYY-MM-DD format.
     */
    private function convertDateFormat(string $date): string
    {
        $parts = explode('/', $date);
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
}
