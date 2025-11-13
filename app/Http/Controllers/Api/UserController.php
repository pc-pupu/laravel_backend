<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordHistory;
use App\Models\Role;
use App\Services\ErrorLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'status' => 'integer|in:0,1',
        ]);

        if ($validator->fails()) {
            // Convert validation errors to user-friendly messages
            $friendlyErrors = [];
            $errors = $validator->errors();
            
            // Convert each error to user-friendly format
            foreach ($errors->messages() as $field => $messages) {
                $friendlyErrors[$field] = array_map(function($message) {
                    return str_replace(
                        [
                            'The name field is required.',
                            'The name has already been taken.',
                            'The email field is required.',
                            'The email has already been taken.',
                            'The email must be a valid email address.',
                            'The password field is required.',
                            'The password must be at least 8 characters.',
                            'The password confirmation does not match.',
                            'The roles.0 must exist.',
                            'The selected roles.0 is invalid.',
                        ],
                        [
                            'Username is required.',
                            'This username is already taken.',
                            'Email is required.',
                            'This email is already registered.',
                            'Please enter a valid email address.',
                            'Password is required.',
                            'Password must be at least 8 characters long.',
                            'Password confirmation does not match.',
                            'One or more selected roles are invalid.',
                            'One or more selected roles are invalid.',
                        ],
                        $message
                    );
                }, $messages);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Please fix the errors below',
                'errors' => $friendlyErrors
            ], 422);
        }

        // Create user
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->status = $request->status ?? 1;
        $user->new_pass_set = 1;
        $user->save();

        // Save password to history
        PasswordHistory::create([
            'uid' => $user->uid,
            'password_hash' => $user->password,
        ]);

        // Assign roles
        if ($request->has('roles') && is_array($request->roles)) {
            $user->roles()->sync($request->roles);
        }

        $user->load('roles');

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::with('roles', 'roles.permissions')->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->uid, 'uid')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->uid, 'uid')],
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'status' => 'integer|in:0,1',
        ]);

        if ($validator->fails()) {
            // Convert validation errors to user-friendly messages
            $friendlyErrors = [];
            $errors = $validator->errors();
            
            // Convert each error to user-friendly format
            foreach ($errors->messages() as $field => $messages) {
                $friendlyErrors[$field] = array_map(function($message) {
                    return str_replace(
                        [
                            'The name field is required.',
                            'The name has already been taken.',
                            'The email field is required.',
                            'The email has already been taken.',
                            'The email must be a valid email address.',
                            'The password field is required.',
                            'The password must be at least 8 characters.',
                            'The password confirmation does not match.',
                            'The roles.0 must exist.',
                            'The selected roles.0 is invalid.',
                        ],
                        [
                            'Username is required.',
                            'This username is already taken.',
                            'Email is required.',
                            'This email is already registered.',
                            'Please enter a valid email address.',
                            'Password is required.',
                            'Password must be at least 8 characters long.',
                            'Password confirmation does not match.',
                            'One or more selected roles are invalid.',
                            'One or more selected roles are invalid.',
                        ],
                        $message
                    );
                }, $messages);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Please fix the errors below',
                'errors' => $friendlyErrors
            ], 422);
        }

        // Update user
        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->has('status') ? $request->status : $user->status;

        // Handle password change with history check
        if ($request->filled('password')) {
            // Check last 3 passwords
            $lastPasswords = PasswordHistory::where('uid', $user->uid)
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->pluck('password_hash')
                ->toArray();

            $newPasswordHash = Hash::make($request->password);

            // Check if new password matches any of the last 3 passwords
            foreach ($lastPasswords as $oldHash) {
                if (Hash::check($request->password, $oldHash)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please fix the errors below',
                        'errors' => [
                            'password' => ['You cannot use any of your last 3 passwords']
                        ]
                    ], 422);
                }
            }

            $user->password = $newPasswordHash;
            $user->new_pass_set = 1;

            // Save to password history
            PasswordHistory::create([
                'uid' => $user->uid,
                'password_hash' => $newPasswordHash,
            ]);
        }

        $user->save();

        // Update roles
        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        $user->load('roles');

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}
