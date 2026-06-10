<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserProfileController extends Controller
{
    public function show(int $uid)
    {
        $row = DB::table('users as u')
            ->leftJoin('users_details as ud', 'ud.uid', '=', 'u.uid')
            ->leftJoin('user_role as ur', 'ur.uid', '=', 'u.uid')
            ->where('u.uid', $uid)
            ->select([
                'u.uid',
                'u.name',
                'u.mail',
                'u.status',
                'ur.rid as role_id',
                'ud.full_name',
                'ud.mobile_no',
                'ud.office_phone_no',
                'ud.division_id',
                'ud.subdiv_id',
            ])
            ->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'User not found.', 'status_code' => 404], 404);
        }

        return response()->json(['status' => 'success', 'data' => $row, 'status_code' => 200]);
    }

    public function update(Request $request, int $uid)
    {
        $data = $request->validate([
            'mail' => ['nullable', 'email', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'regex:/^[0-9]{10}$/'],
            'office_phone_no' => ['nullable', 'string', 'max:20'],
            'division_id' => ['nullable', 'integer'],
            'subdiv_id' => ['nullable', 'integer'],
            'password' => ['nullable', 'string', 'min:6', 'max:20', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&])[0-9A-Za-z!@#$%^&]{6,20}$/'],
        ]);

        $exists = DB::table('users')->where('uid', $uid)->exists();
        if (!$exists) {
            return response()->json(['status' => 'error', 'message' => 'User not found.', 'status_code' => 404], 404);
        }

        DB::beginTransaction();
        try {
            $userUpd = [];
            if (!empty($data['mail'])) {
                $userUpd['mail'] = strtolower(trim($data['mail']));
            }
            if (!empty($data['password'])) {
                $userUpd['password'] = Hash::make($data['password']);
                $userUpd['new_pass_set'] = 1;
            }
            if (!empty($userUpd)) {
                DB::table('users')->where('uid', $uid)->update($userUpd);
            }

            $detailsExists = DB::table('users_details')->where('uid', $uid)->exists();
            $detailsUpd = [
                'full_name' => isset($data['full_name']) ? strtoupper(trim((string) $data['full_name'])) : null,
                'mobile_no' => $data['mobile_no'] ?? null,
                'office_phone_no' => $data['office_phone_no'] ?? null,
                'division_id' => $data['division_id'] ?? 0,
                'subdiv_id' => $data['subdiv_id'] ?? 0,
            ];

            if ($detailsExists) {
                DB::table('users_details')->where('uid', $uid)->update($detailsUpd);
            } else {
                DB::table('users_details')->insert(array_merge(['uid' => $uid], $detailsUpd));
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Profile updated successfully.', 'status_code' => 200]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update profile.', 'status_code' => 500], 500);
        }
    }

    /**
     * Change password with old password verification (Drupal password_change parity).
     */
    public function changePassword(Request $request, int $uid)
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:20', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&])[0-9A-Za-z!@#$%^&]{6,20}$/'],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $user = DB::table('users')->where('uid', $uid)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found.', 'status_code' => 404], 404);
        }

        if (!Hash::check($data['old_password'], $user->password) && !Hash::check($data['old_password'], $user->password_old ?? '')) {
            return response()->json(['status' => 'error', 'message' => 'Current password is incorrect.', 'status_code' => 422], 422);
        }

        DB::table('users')->where('uid', $uid)->update([
            'password' => Hash::make($data['password']),
            'new_pass_set' => 1,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Password changed successfully.', 'status_code' => 200]);
    }
}
