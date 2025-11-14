<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * LIST USERS (used by /admin/users/list)
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Search by name or email (case-insensitive)
        if ($request->search) {
            $searchTerm = strtolower($request->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Optional status filter
        if ($request->status !== null) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    /**
     * CREATE USER
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255|unique:users,name',
            'email'     => 'required|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'roles'     => 'array',
            'roles.*'   => 'exists:roles,id',
            'status'    => 'integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Create user
        $user = new User();
        $user->name         = $request->name;
        $user->email        = $request->email;
        $user->password     = Hash::make($request->password);
        $user->status       = $request->status ?? 1;
        $user->new_pass_set = 1;
        $user->save();

        // Save password to history
        PasswordHistory::create([
            'uid'           => $user->uid,
            'password_hash' => $user->password
        ]);

        // Assign roles
        if ($request->roles) {
            $user->roles()->sync($request->roles);
        }

        $user->load('roles');

        return response()->json([
            'status'  => 'success',
            'message' => 'User created successfully',
            'data'    => $user
        ], 201);
    }

    /**
     * SHOW USER (uses uid, not id)
     */
    public function show($uid)
    {
        $user = User::with('roles', 'roles.permissions')
            ->where('uid', $uid)
            ->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $user
        ]);
    }

    /**
     * UPDATE USER (uses uid)
     */
    public function update(Request $request, $uid)
    {
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255',
                Rule::unique('users', 'name')->ignore($user->uid, 'uid')],
            'email'     => ['required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->uid, 'uid')],
            'password'  => 'nullable|string|min:8|confirmed',
            'roles'     => 'array',
            'roles.*'   => 'exists:roles,id',
            'status'    => 'integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Update basic fields
        $user->name   = $request->name;
        $user->email  = $request->email;
        $user->status = $request->status ?? $user->status;

        /**
         * PASSWORD UPDATE & HISTORY CHECK
         */
        if ($request->filled('password')) {

            // Get last 3 passwords
            $lastPasswords = PasswordHistory::where('uid', $user->uid)
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->pluck('password_hash')
                ->toArray();

            foreach ($lastPasswords as $oldHash) {
                if (Hash::check($request->password, $oldHash)) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Please fix the errors below',
                        'errors'  => [
                            'password' => ['You cannot use any of your last 3 passwords']
                        ]
                    ], 422);
                }
            }

            $newHash = Hash::make($request->password);
            $user->password = $newHash;
            $user->new_pass_set = 1;

            PasswordHistory::create([
                'uid'           => $user->uid,
                'password_hash' => $newHash,
            ]);
        }

        $user->save();

        // Sync roles
        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        $user->load('roles');

        return response()->json([
            'status'  => 'success',
            'message' => 'User updated successfully',
            'data'    => $user
        ]);
    }

    /**
     * DELETE USER
     */
    public function destroy($uid)
    {
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}
