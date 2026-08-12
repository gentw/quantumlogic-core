<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Mail\RegisterWelcomeMail;
use App\Mail\UserJoinWaitListWeb;
use App\Models\Otp;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\RefreshToken;

class AuthenticationController extends Controller
{
    public function test()
    {
        return response()->json('TEST');
    }

    //
    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     summary="Login user and generate OTP (Admins and agents use emails in the phone field, clients use phone numbers)",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"phone", "password"},
     *
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 example="044123456",
     *                 description="Phone number (clients) or email (admins/agents)"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="User's password"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful. OTP generated and sent.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="redirect", type="string", description="Next step URL or checkpoint")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function store(Request $request)
    {

        $request->validate([
            'phone' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL) && ! preg_match('/^\d{8,}$/', $value)) {
                        $fail('Ketu mund te perdoret Numri telefonit ose Email');
                    }
                },
            ],
            'password' => 'required|string|min:6',
        ]);

        if (filter_var(request('phone'), FILTER_VALIDATE_EMAIL)) {

            $userPhone = User::where('phone', request('phone'))->where('role', '!=', 'client')->first();

            if ($userPhone) { // is not client
                if ($userPhone->blocked || $userPhone->deactivated) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to authenticate.',
                    ], 401);
                }

                if (Auth::attempt(['phone' => request('phone'), 'password' => request('password')])) {
                    // successfull authentication
                    $user = User::find(Auth::user()->id);

                    if ($user->role != 'client') {
                        $user = User::where('phone', request('phone'))->first();
                        if ($user->first_time == 1) {
                            User::where('id', $user->id)->where('phone', $user->phone)->where('first_time', 1)->update([
                                'first_time' => 0,
                            ]);
                        }
                        $user_token['token'] = $user->createToken('appToken')->accessToken;

                        return response()->json([
                            'success' => true,
                            'token' => $user_token,
                            'user' => $user,
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to authenticate.',
                    ], 401);
                }
            } else { // for client

                $user = User::where('email', request('phone'))->where('role', 'client')->first();
                if ($user->blocked || $user->deactivated) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to authenticate.',
                    ], 401);
                }

                if (Auth::attempt(['email' => request('phone'), 'password' => request('password')])) {
                    $user = User::find(Auth::user()->id);
                    if ($user->first_time == 1) {
                        User::where('id', $user->id)->where('phone', $user->phone)->where('first_time', 1)->update([
                            'first_time' => 0,
                        ]);
                    }

                    $otp = rand(100000, 999999);
                    $expiresAt = now()->addMinutes(10);

                    Otp::updateOrCreate(
                        [
                            'phone' => request('phone'),  // Use 'phone' to find the record
                        ],
                        [
                            'otp' => $otp,
                            'expires_at' => $expiresAt,
                        ]
                    );
                    // Send OTP via email
                    Mail::to(request('phone'))->send(new OtpMail($otp, $expiresAt));

                    return response()->json(['redirect' => 'checkpoint']);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to authenticate.',
                    ], 401);
                }

            }
        } else {
            $user = User::where('phone', request('phone'))->first();

            if ($user->blocked || $user->deactivated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate.',
                ], 401);
            }

            if (! Hash::check($user->phone, $user->password) && $user->first_time == 1) {
                User::where('id', $user->id)->where('phone', $user->phone)->where('first_time', 1)->update([
                    'password' => Hash::make($user->phone),
                    'first_time' => 0,
                ]);
            }

            if (Auth::attempt(['phone' => request('phone'), 'password' => request('password')])) {
                // successfull authentication
                $user = Auth::user();
                $existsEmail = ($user->email === null ? 'add-user-email' : 'checkpoint');

                if ($user->first_time == 1) {
                    User::where('id', $user->id)->where('phone', $user->phone)->where('first_time', 1)->update([
                        'first_time' => 0,
                    ]);
                }

                if ($existsEmail == 'checkpoint') {
                    $otp = rand(100000, 999999);
                    $expiresAt = now()->addMinutes(10);

                    Otp::updateOrCreate(
                        [
                            'phone' => request('phone'),  // Use 'phone' to find the record
                        ],
                        [
                            'otp' => $otp,
                            'expires_at' => $expiresAt,
                        ]
                    );
                    // Send OTP via email
                    Mail::to($user->email)->send(new OtpMail($otp, $expiresAt));
                }

                // Send OTP via email
                // Mail::to($request->email)->send(new OtpMail($otp));
                // return response()->json(['message' => 'OTP sent to your email.']);
                return response()->json(['redirect' => $existsEmail]);

            } else {
                // failure to authenticate
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate.',
                ], 401);
            }
        }

    }

    /**
     * @OA\Post(
     *     path="/api/v1/logout",
     *     summary="Logout user",
     *     tags={"Auth"},
     *     security={{"passport": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request)
    {
        $token = $request->user()?->token();

        // No token to revoke is the same outcome the caller wanted, so answer 200
        // rather than falling off the end with an empty body. The SPA discards the
        // session either way and must not be left guessing.
        if (! $token) {
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        }

        // Revoking the access token alone leaves its refresh token usable until it
        // expires, so /v1/token/refresh could mint a new access token for a session
        // the user just ended.
        RefreshToken::where('access_token_id', $token->id)->update(['revoked' => true]);

        $token->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/verify-otp",
     *     summary="Verify OTP for user authentication using received OTP code",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *
     *             @OA\Property(property="phone", type="string", format="phone"),
     *             @OA\Property(property="otp", type="string", format="digits", example="123456")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="object",
     *                 @OA\Property(property="token", type="string", example="your_generated_token_here")
     *             ),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="1234567890")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired OTP",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Invalid or expired OTP.")
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'otp' => 'required|digits:6',
        ]);

        $otp = Otp::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if ($otp) {
            if (filter_var($request->phone, FILTER_VALIDATE_EMAIL)) {
                $user = User::where('email', $request->phone)->first();
            } else {
                $user = User::where('phone', $request->phone)->first();
            }

            $user_token['token'] = $user->createToken('appToken')->accessToken;

            $otp->delete();

            return response()->json([
                'success' => true,
                'token' => $user_token,
                'user' => $user,
            ], 200);
        }

        return response()->json(['message' => 'Invalid or expired OTP.'], 401);
    }

    public function registerClientEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|',
        ]);

        $changeEmail = User::where('phone', $request->phone)->where('role', 'client')->update([
            'email' => $request->email,
        ]
        );

        if ($changeEmail) {
            $user = User::where('phone', $request->phone)->where('role', 'client')->first();

            $otp = rand(100000, 999999);
            $expiresAt = now()->addMinutes(10);

            Otp::updateOrCreate(
                [
                    'phone' => request('phone'),  // Use 'phone' to find the record
                ],
                [
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                ]
            );
            // Send OTP via email
            Mail::to($user->email)->send(new OtpMail($otp, $expiresAt));

            return response()->json([
                'success' => true,
                'user' => $user,
            ], 200);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/register-new-client",
     *     summary="Register a new client",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "surname", "email", "phone", "password"},
     *
     *             @OA\Property(property="name", type="string", example="John", description="Client's first name"),
     *             @OA\Property(property="surname", type="string", example="Doe", description="Client's surname"),
     *             @OA\Property(property="email", type="string", example="john.doe@example.com", description="Client's email address"),
     *             @OA\Property(property="phone", type="string", example="044123456", description="Client's phone number"),
     *             @OA\Property(property="password", type="string", format="password", description="Client's password")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Client registered successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true, description="Indicates success"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", description="Client ID"),
     *                 @OA\Property(property="name", type="string", description="Client's name"),
     *                 @OA\Property(property="surname", type="string", description="Client's surname"),
     *                 @OA\Property(property="email", type="string", description="Client's email"),
     *                 @OA\Property(property="phone", type="string", description="Client's phone number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false, description="Indicates failure"),
     *             @OA\Property(property="errors", type="object", description="Validation error messages")
     *         )
     *     )
     * )
     */
    public function registerNewClient(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required|string|min:6',
        ]);

        $newClient = User::create([
            'id' => null,
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            // Whatever SetLocale worked out for this request. Persisted because
            // scheduler-dispatched mail has no request to re-derive it from.
            'locale' => app()->getLocale(),
        ]);

        return response()->json([
            'success' => true,
            'user' => $newClient,
        ], 200);
    }

    public function registerNewClientFromWeb(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required',
            'password' => 'required|string|min:6',
        ]);

        $newClient = User::create([
            'id' => null,
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'locale' => app()->getLocale(),
            'blocked' => 1,
            'deactivated' => 1,
        ]);

        Mail::to($request->email)->send(new UserJoinWaitListWeb($request->name));

        return response()->json([
            'success' => true,
            'user' => $newClient,
        ], 200);
    }

    public function registerClient(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required|string|min:6',
        ]);

        $newClient = User::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'locale' => app()->getLocale(),
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = User::find($newClient->id);

            if ($user->first_time == 1) {
                User::where('id', $user->id)->where('phone', $user->phone)->where('first_time', 1)->update([
                    'first_time' => 0,
                ]);
            }

            $user_token['token'] = $user->createToken('appToken')->accessToken;

            $name = $user->name;
            Mail::to($request->email)->send(new RegisterWelcomeMail($name, $otp, $expiresAt));

            return response()->json([
                'success' => true,
                'token' => $user_token,
                'user' => $user,
            ], 200);

            // $otp = rand(100000, 999999);
            // $expiresAt = now()->addMinutes(10);

            // Otp::updateOrCreate(
            //     [
            //         'phone' => $request->email  // Use 'phone' to find the record
            //     ],
            //     [
            //         'otp' => $otp,
            //         'expires_at' => $expiresAt
            //     ]
            // );
            // Send OTP via email

        }

        return response()->json(['redirect' => 'checkpoint']);

        // return response()->json([
        //     'success' => true,
        //     'user' => $newClient,
        // ], 200);
    }
}
