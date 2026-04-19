<?php

namespace App\Exception;

class BillingException extends \Exception
{
    private array $errors {
        get {
            return $this->errors;
        }
    }
    private int $statusCode {
        get {
            return $this->statusCode;
        }
    }

    public function __construct(string $message, int $statusCode, array $errors = [], ?\Throwable $previous = null) {
        parent::__construct($message, $statusCode, $previous);
        $this->errors = $errors;
        $this->statusCode = $statusCode;
    }

    public static function fromBillingResponse(array $response, int $statusCode, ?string $message): self
    {
        $message = $message ?? 'Billing service error';
        $errors = $response['errors'] ?? [];
        return new self($message, $statusCode, $errors);
    }
}
