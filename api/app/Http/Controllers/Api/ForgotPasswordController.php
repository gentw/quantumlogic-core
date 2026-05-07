<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendCodeResetPassword;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Validator;

class ForgotPasswordController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/password/email",
     *     tags={"Auth"},
     *     summary="Send a password reset link",
     *     description="This endpoint sends a password reset code to the specified email address.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email field is required.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An error occurred while processing your request.")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        // Validate the email field
        $data = $request->validate([
            'email' => [
            'required',
            'email',
                Rule::exists('users', 'email')->where(function ($query) {
                    $query->where('deactivated', 0);
                }),
            ],
        ]);

        ResetCodePassword::where('email', $request->email)->delete();
        // Send the reset link email
       

        $randomNumber = mt_rand(1000, 9999); // Generate a random 4-digit number
        $randomLetters = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6); // Generate random 6-character string
       
        $token = $randomNumber . $randomLetters;

        $data['token'] = $token;

        ResetCodePassword::create($data);
       
        Mail::to($request->email)->send(new SendCodeResetPassword($data['token']));

        return response()->json('success', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/password/reset",
     *     summary="Reset user password",
     *     description="Allows a user to reset their password using a valid token.",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token", "password"},
     *             @OA\Property(property="token", type="string", example="your-token"),
     *             @OA\Property(property="password", type="string", example="newpassword123"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="success"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", 
     *                 @OA\Property(property="token", type="array", @OA\Items(type="string", example="The selected token is invalid.")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="The password must be at least 6 characters.")),
     *             ),
     *             @OA\Property(property="error", type="string", example="expired_code"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="user_not_found"),
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|exists:password_reset_tokens',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // find the code
        $passwordReset = ResetCodePassword::firstWhere('token', $request->token);

        // check if it does not expired: the time is one hour
        if ($passwordReset->created_at > now()->addHour()) {
            $passwordReset->delete();
            return response('expired_code', 422);
        }

        $user = User::firstWhere('email', $passwordReset->email);

        $user->update($request->only('password'));

        ResetCodePassword::where('token', $request->token)->delete();

        return response()->json('success', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/password/token/check",
     *     summary="Check password reset token",
     *     description="Verifies if a password reset token is valid and has not expired.",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="your-token"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token is valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="your-token"),
     *             @OA\Property(property="message", type="string", example="success"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", 
     *                 @OA\Property(property="token", type="array", @OA\Items(type="string", example="The selected token is invalid.")),
     *             ),
     *             @OA\Property(property="error", type="string", example="expired"),
     *         )
     *     )
     * )
     */
    public function checkToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|exists:password_reset_tokens',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // find the code
        $passwordReset = ResetCodePassword::firstWhere('token', $request->token);

        // check if it does not expired: the time is one hour
        if ($passwordReset->created_at > now()->addHour()) {
            ResetCodePassword::where('token', $request->token)->delete();
            return response('expired', 422);
        }

        return response([
            'token' => $passwordReset->token,
            'message' => 'success'
        ], 200);
    }
}
