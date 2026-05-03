<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\BillingException;
use App\Security\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException;

class BillingClient
{
    public function __construct(
        private string $billingUrl,
        private readonly int $billingTimeout = 30
    ) {
        $this->billingUrl = rtrim($billingUrl, '/');
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $payload
     * @param string $jwtToken
     * @return array
     * @throws BillingException
     */
    private function makeCURLRequest(
        string $url,
        string $method = 'GET',
        array $payload = [],
        string $jwtToken = ""
    ): array {
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
                if (!empty($payload)) {
                    $queryString = http_build_query($payload, '', '&');
                    $sep = (!str_contains($url, '?')) ? '?' : '&';
                    curl_setopt($ch, CURLOPT_URL, $url . $sep . $queryString);
                }
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
    public function authenticate(string $email, string $password): array
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
        if (!isset($data['token'], $data['refresh_token'])) {
            $resp = json_encode($data);
            throw new BillingException("Invalid response: tokens not found: {$resp}", 400);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
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
        throw new BillingException($errorMessage, $httpCode);
    }

    /**
     * Получает новый API токен по данному refresh токену
     * @param string $refreshToken
     * @return string
     * @throws BillingException
     */
    public function refreshToken(string $refreshToken): string
    {
        $url = $this->billingUrl.'/api/v1/token/refresh';
        if (empty($refreshToken)) {
            throw new EmptyParameterValueException('Refresh token cannot be empty.');
        }

        $billingResponse = $this->makeCURLRequest($url, 'POST', ["refresh_token" => $refreshToken]);
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        if ($httpCode === 401) {
            throw new BillingException('Unauthorized.', 401);
        }

        if ($httpCode === 400) {
            throw new BillingException('Bad request: ' . json_encode($data), 400, $data['errors']);
        }
        if (!isset($data['refresh_token'])) {
            throw new BillingException("Invalid response: token not found: " . json_encode($data), 500);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data['token'];
        }

        $errorMessage = $decodedResponse['error'] ?? $decodedResponse['message'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }

    public function getCourseList(): array
    {
        $url = $this->billingUrl.'/api/v1/courses';
        $billingResponse = $this->makeCURLRequest($url, 'GET');
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }

        $errorMessage = $decodedResponse['error'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }

    public function getCourseInfo(string $course_code): array
    {
        $url = $this->billingUrl.'/api/v1/courses/'.$course_code;
        $billingResponse = $this->makeCURLRequest($url, 'GET');
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];
        if ($httpCode === 404) {
            throw new BillingException('Course not found.', 404);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }

        $errorMessage = $decodedResponse['error'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }

    public function payCourse(User $user, Course $course): array
    {
        $url = $this->billingUrl.'/api/v1/courses/'.$course->getSymbolicName().'/pay';
        $billingResponse = $this->makeCURLRequest(
            $url,
            'POST',
            jwtToken: $user->getApiToken()
        );
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        if ($httpCode === 406) {
            throw new BillingException('Недостаточно средств.', 406, $data['message']);
        }
        if ($httpCode === 401) {
            throw new BillingException('Unauthorized.', 401, $data['errors']);
        }
        if ($httpCode === 400) {
            throw new BillingException('Bad request: ' . json_encode($data), 400, $data['errors']);
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }
        $errorMessage = $decodedResponse['error'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }

    public function getTransactionHistory(
        User    $user,
        ?string $courseType,
        ?string $courseCode,
        ?bool   $skipExpired
    ): array {
        $url = $this->billingUrl.'/api/v1/transactions';

        $payload = [];
        if (!is_null($courseType)) {
            $payload['type'] = $courseType;
        }
        if (!is_null($courseCode)) {
            $payload['course_code'] = $courseCode;
        }
        if (!is_null($skipExpired)) {
            $payload['skip_expired'] = $skipExpired;
        }

        $billingResponse = $this->makeCURLRequest(
            $url,
            'GET',
            $payload,
            jwtToken: $user->getApiToken()
        );
        $data = $billingResponse['data'];
        $httpCode = $billingResponse['status'];

        if ($httpCode === 401) {
            throw new BillingException('Unauthorized.', 401, $data['errors']);
        }
        if ($httpCode === 400) {
            throw new BillingException('Bad request: ' . json_encode($data), 400, $data['errors']);
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }
        $errorMessage = $decodedResponse['error'] ?? "HTTP Error {$httpCode}";
        throw new BillingException($errorMessage, $httpCode);
    }
}
