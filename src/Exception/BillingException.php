<?php

namespace App\Exception;

class BillingException extends \Exception
{
    private array $errors;
    private int $statusCode;

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
    }public function getErrors(): array
{
    return $this->errors;
}public function getStatusCode(): int
{
    return $this->statusCode;
}
}
