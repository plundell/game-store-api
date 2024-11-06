<?php

declare(strict_types=1);

namespace App\Common\Actions;

use JsonSerializable;

class ResponsePayload implements JsonSerializable
{
    private int $statusCode;

    /**
     * @var array|object|null
     */
    private $data;

    private ?ActionError $error;

    public function __construct(
        int $statusCode = 200,
        $data = null,
        ?ActionError $error = null
    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->error = $error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array|null|object
     */
    public function getData()
    {
        return $this->data;
    }

    public function getError(): ?ActionError
    {
        return $this->error;
    }


    public function jsonSerialize(): array
    {
        $payload = [
            'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }

    static public function json_encode_throw(...$args): string
    {
        $strOrFalse = json_encode(...$args);
        if ($strOrFalse === false) {
            throw new \JsonException(json_last_error_msg());
        } else {
            return (string) $strOrFalse;
        }
    }
}
