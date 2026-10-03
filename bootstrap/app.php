<?php

use App\Exceptions\TemplateMismatchException;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Http\Middleware\RoleMiddleware;
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
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'profile-completed' => EnsureProfileCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render JSON error responses for API routes and for any request that
        // asks for JSON (e.g. the sign-up form's live availability fetch), so a
        // validation failure comes back as 422 JSON rather than an HTML redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A report template uploaded into the wrong slot (or missing its
        // repeating placeholder — typically the Weekly Progress Report design
        // stored as the Timesheet) can't be filled. Send the requester back
        // with the fix-it notice instead of an opaque 500 error page.
        $exceptions->render(function (TemplateMismatchException $e, Request $request) {
            return back()->with('status', $e->getMessage());
        });
    })->create();
