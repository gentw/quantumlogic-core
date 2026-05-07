<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserRequestUpdates;
use App\Http\Requests\UserPreferenceRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    //
    /**
     * @OA\Post(
     *     path="/api/v1/user/preferences/store",
     *     summary="Store user preferences",
     *     description="Stores or updates the authenticated user's preferences.",
     *     operationId="storeUserPreferences",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email_news_and_updates", type="integer", example=1, description="Receive news and updates via email (1 for true, 0 for false)"),
     *             @OA\Property(property="email_tips_and_tutorials", type="integer", example=1, description="Receive tips and tutorials via email"),
     *             @OA\Property(property="email_my_tickets", type="integer", example=1, description="Receive updates about tickets via email"),
     *             @OA\Property(property="email_invoices", type="integer", example=1, description="Receive invoice notifications via email"),
     *             @OA\Property(property="email_reminders", type="integer", example=1, description="Receive reminders via email"),
     *             @OA\Property(property="push_my_tickets", type="integer", example=1, description="Receive push notifications for tickets"),
     *             @OA\Property(property="push_my_comments", type="integer", example=1, description="Receive push notifications for comments"),
     *             @OA\Property(property="push_reminders", type="integer", example=1, description="Receive push reminders"),
     *             @OA\Property(property="push_invoices", type="integer", example=1, description="Receive push notifications for invoices")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="email_news_and_updates", type="integer", example=1),
     *                 @OA\Property(property="email_tips_and_tutorials", type="integer", example=1),
     *                 @OA\Property(property="email_my_tickets", type="integer", example=1),
     *                 @OA\Property(property="email_invoices", type="integer", example=1),
     *                 @OA\Property(property="email_reminders", type="integer", example=1),
     *                 @OA\Property(property="push_my_tickets", type="integer", example=1),
     *                 @OA\Property(property="push_my_comments", type="integer", example=1),
     *                 @OA\Property(property="push_reminders", type="integer", example=1),
     *                 @OA\Property(property="push_invoices", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="email_news_and_updates", type="array", @OA\Items(type="string", example="The email_news_and_updates field is required.")),
     *                 @OA\Property(property="email_tips_and_tutorials", type="array", @OA\Items(type="string", example="The email_tips_and_tutorials field is required.")),
     *                 @OA\Property(property="email_my_tickets", type="array", @OA\Items(type="string", example="The email_my_tickets field is required.")),
     *                 @OA\Property(property="email_invoices", type="array", @OA\Items(type="string", example="The email_invoices field is required.")),
     *                 @OA\Property(property="email_reminders", type="array", @OA\Items(type="string", example="The email_reminders field is required.")),
     *                 @OA\Property(property="push_my_tickets", type="array", @OA\Items(type="string", example="The push_my_tickets field is required.")),
     *                 @OA\Property(property="push_my_comments", type="array", @OA\Items(type="string", example="The push_my_comments field is required.")),
     *                 @OA\Property(property="push_reminders", type="array", @OA\Items(type="string", example="The push_reminders field is required.")),
     *                 @OA\Property(property="push_invoices", type="array", @OA\Items(type="string", example="The push_invoices field is required."))
     *             )
     *         )
     *     )
     * )
     */
    public function storeUserPreferences(UserPreferenceRequest $request) 
    {
        try {
            $validatedData = $request->validated();
            $authId = Auth::user()->id;
    
            UserPreference::updateOrCreate(
                ['user_id' => $authId],
                $validatedData
            );
            return response()->json(['status' => 'success', 'data' => $validatedData]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'errors' => $e->errors()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/preferences/fetch",
     *     summary="Fetch user preferences",
     *     description="Retrieves the preferences for the authenticated user.",
     *     operationId="fetchUserPreferences",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email_news_and_updates", type="integer", example=1, description="Preference for news and updates via email"),
     *             @OA\Property(property="email_tips_and_tutorials", type="integer", example=1, description="Preference for tips and tutorials via email"),
     *             @OA\Property(property="email_my_tickets", type="integer", example=1, description="Preference for ticket updates via email"),
     *             @OA\Property(property="email_invoices", type="integer", example=1, description="Preference for receiving invoices via email"),
     *             @OA\Property(property="email_reminders", type="integer", example=1, description="Preference for reminders via email"),
     *             @OA\Property(property="push_my_tickets", type="integer", example=1, description="Preference for push notifications about tickets"),
     *             @OA\Property(property="push_my_comments", type="integer", example=1, description="Preference for push notifications about comments"),
     *             @OA\Property(property="push_reminders", type="integer", example=1, description="Preference for push notifications about reminders"),
     *             @OA\Property(property="push_invoices", type="integer", example=1, description="Preference for push notifications about invoices")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Preferences not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Preferences not found for the user")
     *         )
     *     )
     * )
     */
    public function fetchUserPreferences() {
        $authId = Auth::user()->id;
        $preferences = UserPreference::where('user_id', $authId)->first();
        return response()->json($preferences);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user/profile/updateUserRequest",
     *     tags={"User"},
     *     summary="Update user request",
     *     description="This endpoint allows the user to submit a request to update their profile, including name, surname, email, phone, city, postal code, address, and avatar.",
     *     operationId="updateUserRequest",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "surname", "email", "phone", "city", "postal_code", "address"},
     *             @OA\Property(property="name", type="string", description="The first name of the user."),
     *             @OA\Property(property="surname", type="string", description="The last name of the user."),
     *             @OA\Property(property="email", type="string", format="email", description="The email of the user."),
     *             @OA\Property(property="phone", type="string", description="The phone number of the user."),
     *             @OA\Property(property="city", type="string", description="The city where the user resides."),
     *             @OA\Property(property="postal_code", type="string", description="The postal code of the user's address."),
     *             @OA\Property(property="address", type="string", description="The full address of the user."),
     *             @OA\Property(property="avatar", type="file", description="The profile image of the user (optional).")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated the user request.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", description="The unique identifier of the update request."),
     *                 @OA\Property(property="name", type="string", description="The full name of the user."),
     *                 @OA\Property(property="email", type="string", description="The email of the user."),
     *                 @OA\Property(property="phone", type="string", description="The phone number of the user."),
     *                 @OA\Property(property="address", type="string", description="The address of the user."),
     *                 @OA\Property(property="city", type="string", description="The city of the user."),
     *                 @OA\Property(property="postal_code", type="string", description="The postal code of the user."),
     *                 @OA\Property(property="img", type="string", description="The URL of the profile image (if uploaded)."),
     *                 @OA\Property(property="user_id", type="integer", description="The user ID associated with this update.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email has already been taken.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized, user not authenticated.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function updateUserRequest(Request $request) {
              
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required',
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'address' => 'required|string',
        ]);
    
        $user_id = Auth::user()->id;
    
        User::where('id', $user_id)->update(['update_request' => 1]);
    
        $updateUser = UserRequestUpdates::create([
            'name'        => $request->name,
            'surname'     => $request->surname,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'address'     => $request->address,
            'city'        => $request->city,
            'postal_code' => $request->postal_code,
            'user_id'     => $user_id,
        ]);
    
       
        if ($request->hasFile('avatar')) {
            if (Auth::user()->img !== null && Storage::disk('public')->exists(Auth::user()->img)) {
                Storage::disk('public')->delete(Auth::user()->img);
            }
            
            $updateUser->img = $request->file('avatar')->storePublicly('images/profile-images', 'public');
            $updateUser->save();
        } else {
            $updateUser->img = Auth::user()->img;
            $updateUser->save();
        }
    
        return response()->json([
            'success' => true,
            'user' => $updateUser,
        ], 200);
    }
    
    /**
     * @OA\Post(
     *     path="/api/v1/user/profile/{user}/approveUpdateUserRequest",
     *     tags={"User"},
     *     summary="Approve a user update request FROM ADMIN",
     *     description="This endpoint allows the admin to approve a user's update request, applying the changes from the update request and deleting the request.",
     *     operationId="approveUpdateUserRequest",
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="The ID of the user whose update request is being approved.",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully approved the update request and applied changes.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", description="The unique identifier of the user."),
     *                 @OA\Property(property="name", type="string", description="The full name of the user."),
     *                 @OA\Property(property="email", type="string", description="The email of the user."),
     *                 @OA\Property(property="phone", type="string", description="The phone number of the user."),
     *                 @OA\Property(property="address", type="string", description="The address of the user."),
     *                 @OA\Property(property="city", type="string", description="The city of the user."),
     *                 @OA\Property(property="postal_code", type="string", description="The postal code of the user."),
     *                 @OA\Property(property="img", type="string", description="The URL of the profile image of the user."),
     *                 @OA\Property(property="update_request", type="integer", description="Indicates if the user has an update request.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Update request not found for the user.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No update request found for this user.")
     *         )
     *     )
     * )
     */
    public function approveUpdateUserRequest(User $user) {
        $userRequest = UserRequestUpdates::where('user_id', $user->id)->first();
    
        if (!$userRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No update request found for this user.',
            ], 404);
        }
    
        $user->update([
            'name' => $userRequest->name,
            'surname' => $userRequest->surname,
            'email' => $userRequest->email,
            'phone' => $userRequest->phone,
            'address' => $userRequest->address,
            'city' => $userRequest->city,
            'postal_code' => $userRequest->postal_code,
            'img' => $userRequest->img,
            'update_request' => 0
        ]);
    
        $userRequest->delete();
    
        return response()->json([
            'success' => true,
            'user' => $user,
        ], 200);
    }

    public function declineUpdateUserRequest(User $user) {
        $userRequest = UserRequestUpdates::where('user_id', $user->id)->first();
    
        if (!$userRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No update request found for this user.',
            ], 404);
        }

        $userRequest->delete();
    
        $user->update([
            'update_request' => 0
        ]);
    
        $userRequest->delete();
    
        return response()->json([
            'success' => true,
            'user' => $user,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/profile/{user}/showUserRequestDataByUserId",
     *     tags={"User"},
     *     summary="Get user request data by user ID",
     *     description="This endpoint retrieves the user request data for a specific user by their user ID.",
     *     operationId="showUserRequestDataByUserId",
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="The ID of the user whose request data is being fetched.",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved the user request data.",
     *         @OA\JsonContent(
     *           @OA\Property(property="user", type="object",
     *           @OA\Property(property="id", type="integer", description="The unique identifier of the update request."),
     *           @OA\Property(property="name", type="string", description="The full name of the user."),
     *                @OA\Property(property="email", type="string", description="The email of the user."),
     *                @OA\Property(property="phone", type="string", description="The phone number of the user."),
     *                @OA\Property(property="address", type="string", description="The address of the user."),
     *                @OA\Property(property="city", type="string", description="The city of the user."),
     *                @OA\Property(property="postal_code", type="string", description="The postal code of the user."),
     *                @OA\Property(property="img", type="string", description="The URL of the profile image (if uploaded)."),
     *                @OA\Property(property="user_id", type="integer", description="The user ID associated with this update.")
     *            )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User request data not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User request data not found.")
     *         )
     *     )
     * )
     */
    public function showUserRequestDataByUserId(User $user) {
        $userRequest = UserRequestUpdates::where('user_id', $user->id)->first();
        return response()->json($userRequest);
    }


    /**
     * @OA\Get(
     *     path="/api/v1/user/profile/{user_id}/showUserRequestData",
     *     tags={"User"},
     *     summary="Get user request data by request ID",
     *     description="This endpoint retrieves the user request data based on the request ID.",
     *     operationId="showUserRequestData",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the user request data being fetched.",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved the user request data.",
     *         @OA\JsonContent(
     *           @OA\Property(property="user", type="object",
     *           @OA\Property(property="id", type="integer", description="The unique identifier of the update request."),
     *           @OA\Property(property="name", type="string", description="The full name of the user."),
     *                @OA\Property(property="email", type="string", description="The email of the user."),
     *                @OA\Property(property="phone", type="string", description="The phone number of the user."),
     *                @OA\Property(property="address", type="string", description="The address of the user."),
     *                @OA\Property(property="city", type="string", description="The city of the user."),
     *                @OA\Property(property="postal_code", type="string", description="The postal code of the user."),
     *                @OA\Property(property="img", type="string", description="The URL of the profile image (if uploaded)."),
     *                @OA\Property(property="user_id", type="integer", description="The user ID associated with this update.")
     *            )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User request data not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User request data not found.")
     *         )
     *     )
     * )
     */
    public function showUserRequestData(UserRequestUpdates $user) {
        $userRequest = UserRequestUpdates::where('id', $user->id)->first();
        return response()->json($userRequest);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user/profile/updatePassword",
     *     summary="Update user password",
     *     description="Updates the user's password after verifying the old password.",
     *     tags={"User"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Password update details",
     *         @OA\JsonContent(
     *             required={"old_password", "password"},
     *             @OA\Property(property="old_password", type="string", example="oldpassword123", description="The user's current password."),
     *             @OA\Property(property="password", type="string", example="newpassword123", description="The new password to be set.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation or password mismatch error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="old_password", type="array",
     *                     @OA\Items(type="string", example="The old password is incorrect.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'password' => 'required|string|min:6', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['errors' => ['old_password' => ['The old password is incorrect.']]], 422);
        }

        $user->update([
            'password' => Hash::make($request->password), 
        ]);

        return response()->json(['message' => 'Password updated successfully'], 200);
    }
    

    public function fetchAuthUserData() {
        $authUser = Auth::user();

        return response()->json($authUser);
    }

    public function blockUnblockUser(Request $request) {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request['user_id']);

        if (!$user) {
            return response()->json(['error' => 'Perdoruesi nuk u gjet!'], 404);
        }
    
        $updateData = match (true) {
            $user->blocked && $user->deactivated => ['blocked' => 0, 'deactivated' => 0],
            !$user->blocked && $user->deactivated => ['deactivated' => 0],
            $user->blocked && !$user->deactivated => ['blocked' => 0],
            default => ['blocked' => 1, 'deactivated' => 1],
        };
    
        $user->update($updateData);
    
        return response()->json([
            "status" => "success",
            "user"   => $user
        ]);
    }


    public function deactivateUser(Request $request) {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request['user_id']);

        if (!$user) {
            return response()->json(['error' => 'Perdoruesi nuk u gjet!'], 404);
        }

        $user->update(
            [
                'deactivated'  => 1
            ]
        );

        return response()->json(
            [
                'status' => 'success',
                'user'   => $user
            ]
        );
    }


    public function showUserDataById($user_id) {
        $user = User::where('id', $user_id)->first();

        if (!$user) {
            return response()->json("no_user_found");
        }

        if ($user->role != 'client') {
            // Check if phone_2 is not null and override phone with it
            if ($user->phone_2 != null) {
                $user->setAttribute('phone', $user->phone_2);
            }

            // Check if phone is not null and override email with it
            if ($user->phone != null) {
                $user->setAttribute('email', $user->email);
            }
        }

        return response()->json($user);
    }

    public function findUserByName(Request $request){
        $users_to_find = User::select(['id', 'name', 'surname', 'img'])->where('name','LIKE', '%'. $request['user'].'%')->limit(10)->get() ->map(function($user) {
            return [
                'id'     => $user->id, 
                'name'   => $user->name . " " . $user->surname,
                'avatar' => $user->img,
            ];
        });
       
        return $users_to_find;
    }


    public function features()
    {
        $user = auth()->user();

        return response()->json([
            'features' => $user->features(),
        ]);
    }

    public function clientProfile(Request $request)
    {
        $user = $request->user();

        // Get active subscription (if any)
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->with('package') // load package info
            ->first();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'package_name' => $subscription->package->name,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'billing_cycle' => $subscription->billing_cycle,
                'auto_renew' => $subscription->auto_renew,
                'features' => $subscription->package->features,
            ] : null,
        ]);
    }

}
