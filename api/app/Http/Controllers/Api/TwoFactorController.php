<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTwoFactorRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The account-settings side of two-factor: read the current state, turn it on
 * or off. Login itself is handled in AuthenticationController.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $this->twoFactor->isRequiredFor($user),
            'default_for_role' => $this->twoFactor->defaultForRole($user->role),
            // The address the code goes to, so the settings card can say where
            // rather than leaving the user to guess.
            'delivery_email' => $user->email ?? $user->phone,
        ]);
    }

    public function update(UpdateTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $enabled = $request->boolean('enabled');

        // Turning it on for an account with no address would lock the owner out
        // at their next login, with no way to receive the code.
        if ($enabled && ! $user->email) {
            return response()->json([
                'message' => 'Add an email address to your account before switching two-factor on.',
                'errors' => ['enabled' => ['No email address on file to send codes to.']],
            ], 422);
        }

        $user->update(['two_factor_enabled' => $enabled]);

        return response()->json([
            'success' => true,
            'enabled' => $user->two_factor_enabled,
        ]);
    }
}
