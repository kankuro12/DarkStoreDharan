<?php

namespace App\Exceptions;

use Exception;

class CartValidationException extends Exception
{
    public function __construct(
        string $message = 'Cart validation failed.',
        public array $details = [],
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }
}
