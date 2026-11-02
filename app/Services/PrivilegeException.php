<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class PrivilegeException extends \RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
