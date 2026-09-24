<?php

namespace App\Exceptions;

use Exception;

class OutOfStockException extends Exception
{
    public function __construct(
        string $message = 'One or more items in your cart are currently out of stock.',
        public array $unavailableItems = [],
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }
}
