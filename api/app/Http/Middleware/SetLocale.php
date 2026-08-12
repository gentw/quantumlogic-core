<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts every API request into the caller's language, so validation messages and
 * any mail sent inline during the request come back translated.
 *
 * Runs on the whole v1 group, including the unauthenticated routes — a visitor
 * on the public checkout or a pay link has no account to read a preference from,
 * which is exactly where the geolocation guess earns its keep.
 */
class SetLocale
{
    public function __construct(private readonly LocaleService $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->locales->resolve($request));

        return $next($request);
    }
}
