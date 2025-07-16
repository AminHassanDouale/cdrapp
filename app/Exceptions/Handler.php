<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    // Probably you don't waana report these.
    protected $dontReport = [
        AppException::class,
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
    }

    // Exceptions that returns JSON pairs with `resources/js/app.js` logic
    public function render($request, Throwable $e)
    {
        $render = parent::render($request, $e);
        $status = $render->getStatusCode();

        // 419 error
        // Useful when deploying new versions
        if ($status == 419) {
            return response()->json(['status' => 419], 500);
        }

        // On this specific scenarios we want to use default Laravel render
        // Notice `AppException` has a custom render
        if (app()->environment() == 'local' || $e instanceof AuthenticationException || $e instanceof AppException) {
            return $render;
        }

        // Afterward this point it displays a custom nice error view

        $title = match ($status) {
            503 => "Come back soon.",
            500 => "Something went wrong on our side.",
            404 => "Page not found.",
            401 => "Not authenticated.",
            403 => "Permission denied.",
            default => "Unknown error."
        };

        return response()->view('errors.error', [
            'isLivewire' => $request->hasHeader('X-Livewire'),
            'title' => $title,
            'detail' => $e->getMessage()
        ], 500);
    }
}
