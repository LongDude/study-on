<?php

namespace App\Service;

use App\Exception\BillingException;
use App\Security\User;
use Symfony\Bundle\SecurityBundle\Security;

class BillingClient
{
    private string $billingUrl;
    private int $billingTimeout;

    public function __construct(
        string $billingUrl,
        int $billingTimeout = 30
    ){
        $this->billingUrl = rtrim($billingUrl, '/');
        $this->billingTimeout = $billingTimeout;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $payload
     * @return array
     * @throws BillingException
     */
    private function makeCURLRequest(
        string $url,
        string $method = 'GET',
        array $payload = [],
        string $jwtToken = ""
    ): array
    {
        // Open cUrl channel
        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($jwtToken) {
            $headers[] = 'Authorization: Bearer ' . $jwtToken;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->billingTimeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Method config
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($payload !== null) {
                    try {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
                    } catch (\JsonException $e) {
                        throw new BillingException($e->getMessage(), $e->getCode(), previous: $e);
                    }
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
                }
                break;
            case 'GET':
                break;
            default:
                throw new BillingException("Unsupported HTTP method: {$method}", 500);
        }

        // Retrieve data
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Process response
        // cURL Error/timeout
        if ($response === false) {
            throw new BillingException("cURL error: {$curlError}", 503);
        }

        // Parse response
        try {
            $decodedResponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), previous: $e);
        }

        // Somehow empty response from Billing
        if ($decodedResponse === null && $response !== '[]') {
            throw new BillingException("Invalid JSON response", 500);
        }

        return [
            "status" => $httpCode,
            "data" => $decodedResponse
        ];
    }

    /**
     *
     * @return string $token - JWT token
     * @throws BillingException
     */
    public function authenticate(string $email, string $password): string
    {
        $url = $this->billingUrl.'/api/v1/auth';
        $billingResponse = $this->makeCURLRequest(
            $url,
            'POST',
            ['username' => $email, 'password' => $password]
        );
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        // Invalid credentials - bad request or nonexisting user
        if ($httpCode === 401) {
            throw new BillingException('Invalid credentials.', 401);
        }
        // No token provided
        if (!isset($data['token'])) {
            $resp = json_encode($data);
            throw new BillingException("Invalid response: token not found: {$resp}", 500);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data['token'];
        }

        // Unknown error
        $errorMessage = $decodedResponse['error'] ?? $decodedResponse['message'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }

    /**
     * @param string $token - jwt token
     * @return User
     * @throws BillingException
     */
    public function getCurrentUser(string $token): User
    {
        $url = $this->billingUrl.'/api/v1/users/current';
        $billingResponse = $this->makeCURLRequest($url, 'GET', [], $token);
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        // Invalid credentials - bad request or nonexisting user
        if ($httpCode === 401) {
            throw new BillingException('Unathorized.', 401);
        }

        if (!isset($data['username'], $data['balance'], $data['roles'])) {
            throw new BillingException('Bad response: ' . json_encode($data), 400);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return new User()
            ->setEmail($data['username'])
            ->setBalance($data['balance'])
            ->setRoles($data['roles']);
        }

        // Unknown error
        $errorMessage = $decodedResponse['error'] ?? $decodedResponse['message'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }

    /**
     * @param string $email
     * @param string $password
     * @return string - JWT token
     * @throws BillingException
     */
    public function register(string $email, string $password): string
    {
        $url = $this->billingUrl.'/api/v1/register';
        $billingResponse = $this->makeCURLRequest($url, 'POST', [
            'email' => $email,
            'password' => $password
        ]);
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        // Constraints violation - response in form {"errors": {"params1" : [], "params2" : []}}
        if ($httpCode === 400) {
            throw new BillingException('Bad request: ' . json_encode($data), 400, $data['errors']);
        }

        // No token provided
        if (!isset($data['token'])) {
            throw new BillingException("Invalid response: token not found: " . json_encode($data), 500);
        }

        // unpack token and return
        if ($httpCode >= 200 && $httpCode < 300) {
            return $data['token'];
        }

        // Unknown error
        $errorMessage = $decodedResponse['error'] ?? $decodedResponse['message'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);    }
}
