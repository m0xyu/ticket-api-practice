<?php

namespace App\Exceptions;

use App\Enums\Errors\ReservationError;
use Illuminate\Http\JsonResponse;

class ReservationException extends \Exception
{
    public function __construct(protected ReservationError $error)
    {
        parent::__construct($error->message(), $error->status());
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->error->message(),
            'error_code' => $this->error->value,
        ], $this->error->status());
    }
}
