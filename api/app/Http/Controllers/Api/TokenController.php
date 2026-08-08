<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\TransientTokenController;

class TokenController extends TransientTokenController
{
    /**
     * @OA\Post(
     *     path="/api/v1/token/refresh",
     *     summary="Refresh access token",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *
     *             @OA\Property(property="refresh_token", type="string", example="your_refresh_token_here"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully refreshed access token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized, invalid refresh token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="invalid_grant"),
     *             @OA\Property(property="message", type="string", example="The refresh token is invalid or expired.")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad Request")
     * )
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required',
        ]);

        // Assuming you have a valid refresh token
        // Logic to create a new access token
        $newToken = $this->issueAccessToken($request);

        return response()->json([
            'success' => true,
            'token' => $newToken,
        ]);
    }
}
