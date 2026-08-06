<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Billing domain exceptions and the HTTP status each maps to. All render
     * as the standard { message } JSON shape — never a per-controller one.
     *
     * @var array<class-string<Throwable>, int>
     */
    private const BILLING_STATUS = [
        InvoiceLockedException::class => 409,
        PaymentAlreadyAppliedException::class => 409,
        InvalidInvoiceStateException::class => 422,
        InvalidOrderStateException::class => 422,
        PaymentGatewayException::class => 502,
    ];

    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, Request $request) {
            $status = self::BILLING_STATUS[$e::class] ?? null;

            if ($status !== null && ($request->expectsJson() || $request->is('api/*'))) {
                return response()->json(['message' => $e->getMessage()], $status);
            }
        });
    }

    // public function render($request, Throwable $exception) {
    //     if ($exception instanceof ValidationException) {
    //         return response()->json([
    //             'message' => $exception->getMessage(),
    //             'errors' => $exception->errors(),
    //         ], 422);
    //     }

    //     return parent::render($request, $exception);
    // }
}
