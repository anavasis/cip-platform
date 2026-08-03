<?php

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Exceptions\ForbiddenException;
use App\Domain\Shared\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\UnauthorizedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'org.access' => \App\Http\Middleware\EnsureOrganizationAccess::class,
            'project.access' => \App\Http\Middleware\EnsureProjectAccess::class,
            'permission' => \App\Http\Middleware\EnforcePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 401);
            }
        });

        $exceptions->render(function (ForbiddenException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
        });

        $exceptions->render(function (NotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 404);
            }
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    })->create();
