<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendUserRegisterConfirmation;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required|string|min:6',
            'address' => 'required|string',
            'city' => 'required|string',
            'postal_code' => 'required|string',
        ]);

        $lastAdminId = User::where('id', '>=', 33333333)->where('role', 'admin')->max('id');

        $newAdmin = User::create([
            'id' => $lastAdminId ? $lastAdminId + 1 : 33333333,
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->email,
            'phone_2' => $request->phone,
            'password' => bcrypt($request->password),
            'role' => 'admin',
            'address' => $request->address,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
        ]);

        if ($request->sendMail) {
            Mail::to($request->email)->send(new SendUserRegisterConfirmation($request->name, 'admin', $request->email, $request->password));
        }

        return response()->json([
            'success' => true,
            'user' => $newAdmin,
        ], 200);
    }

    public function fetchAdmins(Request $request)
    {
        $perPage = $request['perPage'];
        $page = $request['page'];
        $sortBy = $request['sortBy'];
        $sortDesc = $request['sortDesc'];
        $status = $request['status'];

        $q = $request['q'];

        $q = $q ?? '';

        $status = $status ?? '';

        $sortBy = $sortBy ?? 'id';

        $users = User::where(function ($query) use ($q) {
            $query->whereRaw('name like ?', ['%'.$q.'%'])->orWhereRaw(
                'surname like ?', ['%'.$q.'%']
            );
        })->where('role', 'admin');

        $users = $users->orderBy($sortBy, $sortDesc ? 'desc' : 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $total_rows = $users->total();
        $users = $users->items();

        return response()->json([
            'users' => $users,
            'total' => $total_rows,
        ]);
    }

    public function deleteAdmin($user_id)
    {
        if (Auth::id() == $user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ndodhi nje gabim!',
            ], 500);
        }

        $user = User::find($user_id);

        try {
            if ($user) {
                $user->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Admini u fshi me sukses!',
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Ky admin nuk u gjet!',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ndodhi nje gabim!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateAdmin(Request $request, $user_id)
    {
        $user = User::where('id', $user_id)->where('role', 'admin')->first();

        if (! $user) {
            return response()->json(['error' => 'Nuk u gjet asnje admin me kete ID'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users,email,'.$user_id,
            'phone' => 'required|unique:users,phone,'.$user_id,
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update_request = 0;
        $user->update([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->email,
            'phone_2' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->img && Storage::disk('public')->exists($user->img)) {
                Storage::disk('public')->delete($user->img);
            }

            $user->img = $request->file('avatar')->storePublicly('images/profile-images', 'public');
            $user->save();
        }

        return response()->json([
            'success' => true,
            'user' => $user,
        ], 200);
    }

    public function updateAdminPassword(Request $request, $user_id)
    {
        $user = User::where('id', $user_id)->where('role', 'admin')->first();

        if (! $user) {
            return response()->json(['error' => 'Nuk u gjet asnje admin me kete ID'], 404);
        }

        if ($request->killAllSessions) {
            $user = User::findOrFail($user_id);

            // $user->tokens->each(function ($token) {
            //     $token->delete();
            // });

            DB::table('oauth_access_tokens')
                ->where('user_id', $user_id) // Replace $tokenId with the actual token ID
                ->delete();

            // Revoke all of the user's refresh tokens (optional)
            // $user->refreshTokens->each(function ($refreshToken) {
            //     $refreshToken->delete();
            // });
        }

        if ($request->has('blockUser') || $request->has('deactivateUser')) {
            $user->update([
                'blocked' => $request->blockUser,
                'deactivated' => $request->deactivateUser,
            ]);
        }

        if ($request->filled('password')) {
            $validator = Validator::make($request->all(), [
                'password' => 'string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        return response()->json(['status' => 'success'], 200);
    }
}
