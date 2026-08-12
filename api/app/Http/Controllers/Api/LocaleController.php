<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLocaleRequest;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells the SPA which language to start in, and records the user's own choice.
 *
 * The read is public because the login and guest-checkout screens need it
 * before anyone is signed in.
 */
class LocaleController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    /**
     * The locale this visitor should see, plus what else is on offer so the
     * switcher does not have to hard-code the list.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'locale' => $this->locales->resolve($request),
            'supported' => $this->locales->supportedLocales(),
            'fallback' => $this->locales->fallback(),
            'detected_country' => $this->locales->countryFor($request),
        ]);
    }

    /** Persist an explicit choice, which then outranks geolocation everywhere. */
    public function update(UpdateLocaleRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['locale' => $request->validated('locale')]);

        return response()->json([
            'success' => true,
            'locale' => $user->locale,
        ]);
    }
}
