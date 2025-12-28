<?php

namespace App\Attributes;

use Attribute;

#[Attribute]
class ErrorDetails
{
    public function __construct(
        public string $message,
        public int $statusCode
    ) {}
}
