<?php

namespace App\Exceptions;

use Exception;

class QueryValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Query validation failed', int $code = 422)
    {
        $this->errors = $errors;
        parent::__construct($message, $code);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
