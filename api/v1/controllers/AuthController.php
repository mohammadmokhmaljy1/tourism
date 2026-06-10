<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * AuthController.php
 * JWT-based authentication endpoints.
 *
 * Endpoints:
 *   POST /api/v1/auth/register  -> register
 *   POST /api/v1/auth/login     -> login
 *   GET  /api/v1/auth/me        -> me
 *   POST /api/v1/auth/logout    -> logout
 */

class AuthController extends BaseController
{
    private UserRepository $userRepository;
    private RevokedTokenRepository $revokedTokenRepository;

    private const PASSWORD_MIN = 6;

    public function __construct()
    {
        $this->userRepository         = new UserRepository();
        $this->revokedTokenRepository = new RevokedTokenRepository();
    }

    /**
     * POST /api/v1/auth/register
     * Creates a new user account and returns a JWT.
     */
    public function register(): void
    {
        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->required('email')
            ->email('email')
            ->maxLength('email', 255)
            ->required('password')
            ->minLength('password', self::PASSWORD_MIN)
            ->maxLength('profile_picture', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $email = strtolower(trim((string) $data['email']));

        if ($this->userRepository->emailExists($email)) {
            Response::unprocessable('Validation failed.', [
                'email' => 'This email address is already in use.',
            ]);
        }

        $record = [
            'name'            => trim((string) $data['name']),
            'email'           => $email,
            'password'        => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'role'            => 'user',
            'status'          => 'Active',
            'profile_picture' => $this->optionalString($data, 'profile_picture'),
        ];

        $userId = $this->userRepository->create($record);
        $user   = $this->userRepository->findById($userId);

        Response::created('Registration successful.', $this->authPayload($userId, $user));
    }

    /**
     * POST /api/v1/auth/login
     * Validates credentials and returns a JWT.
     */
    public function login(): void
    {
        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('email')
            ->email('email')
            ->required('password');

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $email = strtolower(trim((string) $data['email']));
        $user  = $this->userRepository->findByEmailForAuth($email);

        if ($user === null || !password_verify((string) $data['password'], (string) $user['password'])) {
            Response::unauthorized('Invalid email or password.');
        }

        if ($user['status'] === 'Suspended') {
            Response::unauthorized('Your account has been suspended.');
        }

        unset($user['password']);

        Response::ok('Login successful.', $this->authPayload((int) $user['id'], $user));
    }

    /**
     * GET /api/v1/auth/me
     * Returns the currently authenticated user.
     */
    public function me(): void
    {
        $claims = $this->resolveAuthenticatedUser();
        $user   = $this->userRepository->findById($claims['sub']);

        if ($user === null) {
            Response::unauthorized('User account is no longer available.');
        }

        Response::ok('Authenticated user retrieved successfully.', $user);
    }

    /**
     * POST /api/v1/auth/logout
     * Revokes the current JWT so it cannot be reused.
     */
    public function logout(): void
    {
        $claims = $this->resolveAuthenticatedUser();

        if (!$this->revokedTokenRepository->isRevoked($claims['jti'])) {
            $this->revokedTokenRepository->revoke($claims['jti'], $claims['exp']);
        }

        Response::ok('Logout successful.');
    }

    /**
     * Validates the Bearer token and ensures it has not been revoked.
     *
     * @return array{sub:int,jti:string,exp:int}
     */
    private function resolveAuthenticatedUser(): array
    {
        $claims = Jwt::requireBearer();

        if ($this->revokedTokenRepository->isRevoked($claims['jti'])) {
            Response::unauthorized('Token has been revoked.');
        }

        return $claims;
    }

    /**
     * Builds the standard auth response: user profile + token metadata.
     *
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    private function authPayload(int $userId, ?array $user): array
    {
        $issued = Jwt::issue($userId);

        return [
            'user'        => $user,
            'token'       => $issued['token'],
            'token_type'  => 'Bearer',
            'expires_in'  => JwtConfig::TTL_SECONDS,
            'expires_at'  => $issued['expires_at'],
        ];
    }
}
