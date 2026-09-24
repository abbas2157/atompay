<?php

namespace App\Exceptions\Api;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A request that is well-formed but not allowed in the customer's current
 * state, e.g. applying for a limit before submitting identity details.
 * Rendered as 409 with a stable machine-readable `code` the app switches on
 * (listed in docs/api/conventions.md); `message` is for display.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], 409);
    }
}
