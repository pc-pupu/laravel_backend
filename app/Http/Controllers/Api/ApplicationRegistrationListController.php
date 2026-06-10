<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationRegistrationListController extends Controller
{
    public function index()
    {
        $rows = DB::table('users as u')
            ->join('user_role as ur', 'ur.uid', '=', 'u.uid')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'u.uid')
            ->where('u.status', 0)
            ->where('ur.rid', 4)
            ->orderBy('u.uid')
            ->select([
                'u.uid',
                'u.name as username',
                'u.mail',
                'ha.applicant_name',
                'ha.gender',
                'ha.mobile_no',
                'ha.date_of_birth',
            ])
            ->get()
            ->map(function ($row) {
                $row->gender_label = $row->gender === 'M' ? 'Male' : ($row->gender === 'F' ? 'Female' : $row->gender);
                $row->date_of_birth_display = $row->date_of_birth
                    ? implode('/', array_reverse(explode('-', $row->date_of_birth)))
                    : '';
                $row->document_available = false;

                return $row;
            });

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function updateStatus(Request $request, int $uid, string $action)
    {
        if (!in_array($action, ['activate', 'reject'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid action.'], 422);
        }

        $user = DB::table('users as u')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'u.uid')
            ->where('u.uid', $uid)
            ->where('u.status', 0)
            ->select('u.uid', 'u.name', 'u.mail', 'ha.mobile_no')
            ->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Pending registration not found.'], 404);
        }

        try {
            if ($action === 'activate') {
                DB::table('users')->where('uid', $uid)->update(['status' => 1]);

                $message = '<html><body>Dear Applicant,<br><br>'
                    . 'Your Account has been activated. Please find below your login details. Please change your password after login.<br><br>'
                    . 'Username - ' . $user->name . '<br><br>'
                    . 'Password - ' . $user->name . '<br><br>'
                    . 'Please login using your username and password to apply.<br><br>'
                    . 'Regards,<br>Housing Department<br>Government of West Bengal</body></html>';

                if (!empty($user->mail)) {
                    app(NotificationService::class)->sendMail(
                        $user->mail,
                        'Applicant Registration Approve',
                        $message
                    );
                }

                return response()->json(['status' => 'success', 'message' => 'Account has been activated.']);
            }

            DB::table('housing_applicant_official_detail')->where('uid', $uid)->delete();
            DB::table('housing_applicant')->where('uid', $uid)->delete();
            DB::table('user_role')->where('uid', $uid)->delete();
            DB::table('users_details')->where('uid', $uid)->delete();
            DB::table('users')->where('uid', $uid)->delete();

            return response()->json(['status' => 'success', 'message' => 'Account has been deleted.']);
        } catch (\Throwable $e) {
            Log::error('Registration status update failed', ['uid' => $uid, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Failed to update registration status.'], 500);
        }
    }
}
