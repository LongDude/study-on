<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class TokenDecoderService
{

    /**
     * Декодирует полученный API Token
     * По умолчанию JWT токен содержит поля `iat`, `exp`, `roles`, `username`
     * @param string $apiToken
     * @return array
     */
    public function decodeApiToken(string $apiToken): array
    {
        return $this->decode($apiToken);
    }

    public function getTokenExpiration(string $apiToken): int
    {
        $payload = $this->decode($apiToken);

        if (!is_numeric($payload['exp'])) {
            throw new InvalidParameterException("Malformed token: no expiration time");
        }

        return (int) $payload['exp'];
    }

    /**
     * Декодирует полученный API Token
     * По умолчанию JWT токен содержит поля `iat`, `exp`, `roles`, `username`
     * @param string $apiToken
     * @return array
     */
    private function decode(string $apiToken): array
    {
        if ($apiToken === '') {
            throw new EmptyParameterValueException('Api token cannot be empty.');
        }

        $parts = explode('.', $apiToken);
        if (count($parts) !== 3) {
            throw new InvalidParameterException('Invalid api token.');
        }

        $payload = json_decode(base64_decode($parts[1]), true);
        if (is_null($payload)) {
            throw new InvalidParameterException('Invalid api token.');
        }

        // Validation
        if (!array_key_exists('exp', $payload) ||
            !array_key_exists('iat', $payload) ||
            !array_key_exists('roles', $payload) ||
            !is_array($payload['roles']) ||
            !array_key_exists('username', $payload)
        ) {
            throw new InvalidParameterException('Invalid api token.');
        }

        return $payload;
    }
}
