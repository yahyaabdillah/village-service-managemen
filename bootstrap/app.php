<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The template builder talks to the app with fetch() and "Accept: application/json";
        // without this, its validation errors came back as an HTML redirect that the
        // JSON parser then choked on.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Application services (document generation, malware scanning, WhatsApp) throw a
        // plain RuntimeException carrying a message written for the clerk. Show that
        // message on the page they came from instead of a 500. Only the exact class is
        // caught: framework exceptions that extend RuntimeException (404, 419, ...) keep
        // their own rendering.
        $exceptions->render(function (RuntimeException $exception, Request $request) {
            if ($exception::class !== RuntimeException::class) {
                return null;
            }

            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['error' => $exception->getMessage()]);
        });
    })->create();
