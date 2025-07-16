<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

/**
 * This custom domain exception will be converted into a Toast.
 *
 * See `app/exceptions/Handler.php`
 */
class AppException extends Exception
{
    public function __construct(string $message = "", public ?string $description = null)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $toast = [
            'title' => $this->message,
            'description' => $this->description,
            'position' => 'toast-top toast-end',
            'icon' => Blade::render("<x-mary-icon class='w-7 h-7' name='o-x-circle' />"),
            'timeout' => '3000',
            'css' => 'alert-error'
        ];

        return response()->json(['toast' => $toast], 500);
    }
}
