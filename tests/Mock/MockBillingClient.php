<?php

namespace App\Tests\Mock;

use App\Entity\Course;
use App\Exception\BillingException;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException;

class MockBillingClient extends BillingClient
{
    private const float INITIAL_BALANCE = 3000.0;
    private const int RENT_DAYS = 30;
    private const string TOKEN_ISSUED_AT = '2026-01-01 00:00:00';
    private const string TOKEN_EXPIRES_AT = '2100-01-01 00:00:00';

    private array $credentials = [];
    private array $tokenCache = [];
    private array $refreshTokenCache = [];
    private array $courses = [];
    private array $transactions = [];
    private int $nextTransactionId = 1;

    public function __construct()
    {
        parent::__construct("empty_billing");

        $this->courses = [
            'web-development-basics' => [
                'code' => 'web-development-basics',
                'type' => 'free',
                'price' => 0.0,
            ],
            'python-for-data-science' => [
                'code' => 'python-for-data-science',
                'type' => 'free',
                'price' => 0.0,
            ],
            'symfony-framework-mastery' => [
                'code' => 'symfony-framework-mastery',
                'type' => 'rent',
                'price' => 199.99,
            ],
            'sql-database-design' => [
                'code' => 'sql-database-design',
                'type' => 'buy',
                'price' => 5000.0,
            ],
            'docker-for-developers' => [
                'code' => 'docker-for-developers',
                'type' => 'buy',
                'price' => 4000.0,
            ],
        ];

        $this->addUser(
            'admin@test.local',
            'admin_password',
            self::INITIAL_BALANCE,
            ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
            'mock-admin-refresh-token',
        );
        $this->addUser(
            'user@test.local',
            'user_password',
            self::INITIAL_BALANCE,
            ['ROLE_USER'],
            'mock-user-refresh-token',
        );

        $this->addTransaction('user@test.local', 'web-development-basics', 0.0, '-1 day');
        $this->addTransaction('user@test.local', 'python-for-data-science', 0.0, '-1 day');
        $this->addTransaction('user@test.local', 'sql-database-design', 5000.0, '-15 days');
        $this->addTransaction('user@test.local', 'docker-for-developers', 4000.0, '-30 days');
        $this->addTransaction('user@test.local', 'symfony-framework-mastery', 199.99, '-75 days', '-45 days');
        $this->addTransaction('user@test.local', 'symfony-framework-mastery', 199.99, '-45 days', '-15 days');
        $this->addTransaction('user@test.local', 'symfony-framework-mastery', 199.99, '-15 days', '+15 days');
    }

    private function encodeToken(
        string $username,
        \DateTime $iat,
        \DateTime $exp,
        array $roles = ["ROLE_USER"]
    ): string {
        $apiTokenPayload = base64_encode(json_encode([
            'exp' => $exp->getTimestamp(),
            'iat' => $iat->getTimestamp(),
            'roles' => $roles,
            'username' => $username,
        ]));

        return implode(
            '.',
            [
                base64_encode('mock-header'),
                $apiTokenPayload,
                base64_encode(hash('sha256', $apiTokenPayload, true))
            ]
        );
    }

    private function addUser(
        string $email,
        string $password,
        float $balance,
        array $roles,
        ?string $refreshToken = null
    ): string {
        $token = $this->encodeToken(
            $email,
            new \DateTime(self::TOKEN_ISSUED_AT),
            new \DateTime(self::TOKEN_EXPIRES_AT),
            $roles
        );
        $refreshToken ??= 'mock-refresh-token-'.base64_encode(random_bytes(16));

        $this->credentials[$email] = [
            'password' => $password,
            'token' => $token,
            'refreshToken' => $refreshToken,
        ];
        $this->tokenCache[$token] = new User()
            ->setApiToken($token)
            ->setRefreshToken($refreshToken)
            ->setEmail($email)
            ->setBalance($balance)
            ->setRoles($roles);
        $this->refreshTokenCache[$refreshToken] = $token;

        return $token;
    }

    private function addTransaction(
        string $email,
        ?string $courseCode,
        float $amount,
        string $createdAtModifier = 'now',
        ?string $validUntilModifier = null,
        string $type = 'payment'
    ): array {
        $transaction = [
            'id' => $this->nextTransactionId++,
            'email' => $email,
            'created_at' => (new \DateTime())->modify($createdAtModifier),
            'type' => $type,
            'amount' => $amount,
        ];

        if ($courseCode !== null) {
            $transaction['course_code'] = $courseCode;
        }
        if ($validUntilModifier !== null) {
            $transaction['valid_until'] = (new \DateTime())->modify($validUntilModifier);
        }

        $this->transactions[] = $transaction;

        return $transaction;
    }

    public function authenticate(string $email, string $password): array
    {
        if (array_key_exists($email, $this->credentials) && $this->credentials[$email]['password'] === $password ) {
            return [
                "token" => $this->credentials[$email]['token'],
                "refresh_token" => $this->credentials[$email]['refreshToken'],
            ];
        }
        throw new BillingException('Invalid credentials.', 401);
    }

    public function getCurrentUser(string $token): User
    {
        if (array_key_exists($token, $this->tokenCache)) {
            return $this->tokenCache[$token];
        }
        throw new BillingException('Unauthorized.', 401);
    }

    public function register(string $email, string $password): string
    {
        if (array_key_exists($email, $this->credentials)) {
            throw new BillingException('Bad request.', 400, [
                'email' => ['User already exists.'],
            ]);
        }
        $token = $this->addUser($email, $password, self::INITIAL_BALANCE, ['ROLE_USER']);
        $this->addTransaction($email, null, self::INITIAL_BALANCE, 'now', type: 'deposit');

        return $token;
    }

    public function refreshToken(string $refreshToken): string
    {
        if (empty($refreshToken)) {
            throw new EmptyParameterValueException('Refresh token cannot be empty.');
        }

        if (array_key_exists($refreshToken, $this->refreshTokenCache)) {
            return $this->refreshTokenCache[$refreshToken];
        }
        throw new BillingException('Unauthorized.', 401);
    }

    public function getCourseList(): array
    {
        return array_map(
            static fn (array $course): array => self::formatCourse($course),
            array_values($this->courses),
        );
    }

    public function getCourseInfo(string $course_code): array
    {
        if (!isset($this->courses[$course_code])) {
            throw new BillingException('Course not found.', 404);
        }

        return self::formatCourse($this->courses[$course_code]);
    }

    public function payCourse(User $user, Course $course): array
    {
        $billingUser = $this->getCurrentUser((string) $user->getApiToken());
        $courseInfo = $this->getCourseInfo((string) $course->getSymbolicName());
        $price = (float) ($courseInfo['price'] ?? 0);

        if ((float) $billingUser->getBalance() < $price) {
            throw new BillingException('Недостаточно средств.', 406, ['На вашем счету недостаточно средств']);
        }

        $billingUser->setBalance((float) $billingUser->getBalance() - $price);
        $transaction = $this->addTransaction(
            (string) $billingUser->getEmail(),
            $courseInfo['code'],
            $price,
            'now',
            $courseInfo['type'] === 'rent' ? '+'.self::RENT_DAYS.' days' : null,
        );

        $response = [
            'success' => true,
            'course_type' => $courseInfo['type'],
        ];

        if (isset($transaction['valid_until'])) {
            $response['expires_at'] = $transaction['valid_until']->format('c');
        }

        return $response;
    }

    public function getActiveCourses(User $user): array
    {
        $billingUser = $this->getCurrentUser((string) $user->getApiToken());
        $activeCourses = [];

        foreach ($this->transactions as $transaction) {
            if ($transaction['email'] !== $billingUser->getEmail() || $transaction['type'] !== 'payment') {
                continue;
            }
            if (!isset($transaction['course_code'])) {
                continue;
            }
            if (isset($transaction['valid_until']) && $transaction['valid_until'] <= new \DateTime()) {
                continue;
            }

            $row = ['code' => $transaction['course_code']];
            if (isset($transaction['valid_until'])) {
                $row['valid_until'] = $transaction['valid_until']->format('c');
            }
            $activeCourses[$transaction['course_code']] = $row;
        }

        return array_values($activeCourses);
    }

    public function getTransactionHistory(User $user, ?string $courseType = null, ?string $courseCode = null, ?bool $skipExpired = null): array
    {
        $billingUser = $this->getCurrentUser((string) $user->getApiToken());
        $transactions = [];

        foreach ($this->transactions as $transaction) {
            if ($transaction['email'] !== $billingUser->getEmail()) {
                continue;
            }
            if ($courseType !== null && $transaction['type'] !== $courseType) {
                continue;
            }
            if ($courseCode !== null && ($transaction['course_code'] ?? null) !== $courseCode) {
                continue;
            }
            if ($skipExpired && isset($transaction['valid_until']) && $transaction['valid_until'] <= new \DateTime()) {
                continue;
            }

            $row = [
                'id' => $transaction['id'],
                'created_at' => $transaction['created_at']->format('c'),
                'type' => $transaction['type'],
                'amount' => $transaction['amount'],
            ];
            if (isset($transaction['course_code'])) {
                $row['course_code'] = $transaction['course_code'];
            }

            $transactions[] = $row;
        }

        return $transactions;
    }

    private static function formatCourse(array $course): array
    {
        $formatted = [
            'code' => $course['code'],
            'type' => $course['type'],
        ];

        if ($course['type'] !== 'free') {
            $formatted['price'] = $course['price'];
        }

        return $formatted;
    }
}
