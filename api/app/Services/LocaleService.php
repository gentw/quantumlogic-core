<?php

namespace App\Services;

use App\Models\User;
use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Decides which language a given request or user sees.
 *
 * Resolution order, first hit wins:
 *
 *   1. the user's saved choice (`users.locale`)
 *   2. the country the request came from (Cloudflare header, then GeoIP)
 *   3. the browser's Accept-Language
 *   4. `app.fallback_locale`
 *
 * Geolocation is a guess, so it never overrides an explicit choice. The saved
 * choice also matters off the request cycle: dunning and recurring-charge mail
 * is dispatched from the scheduler, where there is no IP to look at.
 */
class LocaleService
{
    /** Resolve the locale for a request, preferring the user's saved choice. */
    public function resolve(Request $request, ?User $user = null): string
    {
        $user ??= $this->authenticatedUser();

        return $this->forUser($user)
            ?? $this->fromCountry($this->countryFor($request))
            ?? $this->fromAcceptLanguage($request)
            ?? $this->fallback();
    }

    /**
     * The bearer-token user, if there is one.
     *
     * Asks the `api` guard by name: the default guard is `web`, so
     * `$request->user()` is null inside the api middleware group, which runs
     * before any route-level `auth:api`. Never throws — an absent, expired or
     * malformed token just means there is no preference to read, and the
     * caller falls through to geolocation.
     */
    private function authenticatedUser(): ?User
    {
        try {
            $user = Auth::guard('api')->user();

            return $user instanceof User ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The locale stored against a user, or null when they have not chosen one
     * (or chose one that is no longer supported).
     */
    public function forUser(?User $user): ?string
    {
        return $this->supported($user?->locale);
    }

    /** The locale a country maps to, or null when it maps nowhere. */
    public function fromCountry(?string $countryCode): ?string
    {
        if (! $countryCode) {
            return null;
        }

        $map = config('locale.country_map', []);

        return $this->supported($map[strtoupper($countryCode)] ?? null);
    }

    /**
     * The ISO-3166 country the request came from.
     *
     * Cloudflare's CF-IPCountry is preferred: it costs nothing and is already
     * resolved at the edge. The GeoIP lookup only runs when the package and the
     * database are both present.
     */
    public function countryFor(Request $request): ?string
    {
        $header = $request->header('CF-IPCountry');

        // Cloudflare sends XX for unknown and T1 for Tor exit nodes.
        if ($header && ! in_array(strtoupper($header), ['XX', 'T1'], true)) {
            return strtoupper($header);
        }

        return $this->countryFromGeoIp($request->ip());
    }

    /**
     * First supported language from the Accept-Language header.
     *
     * Matches on the primary subtag, so `de-AT` and `de-CH` both resolve to
     * `de`. Laravel has already sorted the header by q-value.
     */
    public function fromAcceptLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $candidate = $this->supported(strtolower(substr($language, 0, 2)));

            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return string[] every locale the app can render */
    public function supportedLocales(): array
    {
        return config('locale.supported', ['en']);
    }

    public function fallback(): string
    {
        return config('app.fallback_locale', 'en');
    }

    /** Null unless the code is one we can actually render. */
    private function supported(?string $locale): ?string
    {
        if (! $locale) {
            return null;
        }

        return in_array($locale, $this->supportedLocales(), true) ? $locale : null;
    }

    /**
     * Country from the MaxMind database, when it is installed.
     *
     * Never throws: an unresolvable address is the normal case for local and
     * private IPs, and a missing database is a valid deployment.
     */
    private function countryFromGeoIp(?string $ip): ?string
    {
        $database = config('locale.geoip_database');

        if (! $ip || ! $database || ! class_exists(Reader::class) || ! is_file($database)) {
            return null;
        }

        try {
            return (new Reader($database))->country($ip)->country->isoCode;
        } catch (\Throwable $e) {
            // AddressNotFound for private ranges is expected and not worth a log
            // line on every request; anything else is worth knowing about.
            if (! str_contains($e->getMessage(), 'is not in the database')) {
                Log::warning('GeoIP country lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            }

            return null;
        }
    }
}
