<?php

namespace Commerce\Vouchers\Exceptions;

use Exception;

class APIError extends Exception
{
    private int $statusCode;
    private mixed $responseBody;

    public function __construct(string $message, int $statusCode, mixed $responseBody = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }
}
