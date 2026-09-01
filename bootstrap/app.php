<?php

declare(strict_types=1);

use App\Http\Middleware\BearerTokenMiddleware;
use App\Http\Middleware\JwtAuthentication;
use App\Http\Middleware\MachineTokenMiddleware;
use App\Http\Middleware\PartnerApiAuth;
use App\Http\Middleware\QueryLogMiddleware;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Sales\Domain\Exceptions\CustomerNotFoundException;
use Src\Sales\Domain\Exceptions\ProductNotFoundException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCancelledException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCompletedException;
use Src\Sales\Domain\Exceptions\SaleCannotBeConfirmedException;
use Src\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__ . '/../routes/web.php',
        api:      __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(QueryLogMiddleware::class);

        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'api.auth' => BearerTokenMiddleware::class,
            'jwt.auth' => JwtAuthentication::class,
            'machine.token' => MachineTokenMiddleware::class,
            'partner.api.auth' => PartnerApiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Report exceptions to Bugsnag (especially important for queue workers)
        $exceptions->report(function (Throwable $e) {
            if (app()->bound('bugsnag')) {
                Bugsnag::notifyException($e);
            }
        });
        // Map Sales domain / port exceptions to proper HTTP status codes so
        // the HTTP adapter layer stays thin (Hexagonal Architecture).
        $exceptions->render(function (PaymentFailedException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'payment_failed',
                    'message' => $e->getMessage(),
                ], 402); // 402 Payment Required
            }
            return null;
        });

        $exceptions->render(function (PaymentGatewayException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'payment_gateway_error',
                    'message' => $e->getMessage(),
                ], 502); // 502 Bad Gateway
            }
            return null;
        });

        $exceptions->render(function (SaleNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'sale_not_found',
                    'message' => $e->getMessage(),
                ], 404);
            }
            return null;
        });

        $exceptions->render(function (CustomerNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'customer_not_found',
                    'message' => $e->getMessage(),
                ], 404);
            }
            return null;
        });

        $exceptions->render(function (ProductNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'product_not_found',
                    'message' => $e->getMessage(),
                ], 404);
            }
            return null;
        });

        $exceptions->render(function (SaleCannotBeConfirmedException|SaleCannotBeCompletedException|SaleCannotBeCancelledException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'invalid_sale_state',
                    'message' => $e->getMessage(),
                ], 409); // 409 Conflict
            }
            return null;
        });

        // Value-object / DTO validation failures thrown from Request::getDto()
        // (missing/invalid fields, bad enums, malformed UUIDs, etc.) become
        // 422 Unprocessable Entity — the standard for well-formed but
        // semantically invalid input.
        $exceptions->render(function (\InvalidArgumentException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'   => 'validation_failed',
                    'message' => $e->getMessage(),
                ], 422);
            }
            return null;
        });
    })->create();
