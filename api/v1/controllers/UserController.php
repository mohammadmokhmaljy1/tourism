<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * UserController.php
 * Handles HTTP request/response and validation for the `users`
 * resource. Database work is delegated to UserRepository.
 *
 * Security notes:
 *   - Passwords are hashed with password_hash() before storage and
 *     are never returned in any response.
 *   - Email uniqueness is enforced before insert/update.
 *
 * Endpoints:
 *   GET    /api/v1/users          -> index   (paginate + search)
 *   GET    /api/v1/users/{id}     -> show
 *   POST   /api/v1/users          -> store
 *   PUT    /api/v1/users/{id}     -> update
 *   DELETE /api/v1/users/{id}     -> destroy (soft delete)
 */

class UserController extends BaseController
{
    private UserRepository $repository;

    /** Allowed values for the `role` and `status` ENUM columns. */
    private const ROLES    = ['user', 'admin'];
    private const STATUSES = ['Active', 'Suspended'];

    /** Minimum accepted password length (raw, before hashing). */
    private const PASSWORD_MIN = 6;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    /**
     * GET /api/v1/users
     */
    public function index(): void
    {
        $params = $this->paginationParams();

        $users = $this->repository->getAll($params['limit'], $params['offset'], $params['search']);
        $total = $this->repository->countAll($params['search']);

        Response::ok('Users retrieved successfully.', [
            'items'      => $users,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    /**
     * GET /api/v1/users/{id}
     */
    public function show(string $id): void
    {
        $userId = $this->parseId($id);

        $user = $this->repository->findById($userId);

        if ($user === null) {
            Response::notFound("User with id {$userId} was not found.");
        }

        Response::ok('User retrieved successfully.', $user);
    }

    /**
     * POST /api/v1/users
     */
    public function store(): void
    {
        $data = $this->getJsonBody();

        // On create, the password is required.
        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->required('email')
            ->email('email')
            ->maxLength('email', 255)
            ->required('password')
            ->minLength('password', self::PASSWORD_MIN)
            ->maxLength('profile_picture', 255)
            ->inList('role', self::ROLES)
            ->inList('status', self::STATUSES);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $email = strtolower(trim((string) $data['email']));

        // Enforce the UNIQUE email constraint with a clear message.
        if ($this->repository->emailExists($email)) {
            Response::unprocessable('Validation failed.', [
                'email' => 'This email address is already in use.',
            ]);
        }

        $record = [
            'name'            => trim((string) $data['name']),
            'email'           => $email,
            'password'        => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'role'            => $this->valueOrDefault($data, 'role', 'user'),
            'status'          => $this->valueOrDefault($data, 'status', 'Active'),
            'profile_picture' => $this->optionalString($data, 'profile_picture'),
        ];

        $newId = $this->repository->create($record);
        $user  = $this->repository->findById($newId);

        Response::created('User created successfully.', $user);
    }

    /**
     * PUT /api/v1/users/{id}
     * Full update. Password is optional here: omit it to keep the
     * current password, or send a new one to change it.
     */
    public function update(string $id): void
    {
        $userId = $this->parseId($id);

        if ($this->repository->findById($userId) === null) {
            Response::notFound("User with id {$userId} was not found.");
        }

        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->required('email')
            ->email('email')
            ->maxLength('email', 255)
            ->minLength('password', self::PASSWORD_MIN)
            ->maxLength('profile_picture', 255)
            ->inList('role', self::ROLES)
            ->inList('status', self::STATUSES);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $email = strtolower(trim((string) $data['email']));

        // Email must stay unique, ignoring this same user's row.
        if ($this->repository->emailExists($email, $userId)) {
            Response::unprocessable('Validation failed.', [
                'email' => 'This email address is already in use.',
            ]);
        }

        $record = [
            'name'            => trim((string) $data['name']),
            'email'           => $email,
            'role'            => $this->valueOrDefault($data, 'role', 'user'),
            'status'          => $this->valueOrDefault($data, 'status', 'Active'),
            'profile_picture' => $this->optionalString($data, 'profile_picture'),
        ];

        // Only hash + update the password when a new one was supplied.
        if (isset($data['password']) && trim((string) $data['password']) !== '') {
            $record['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        $this->repository->update($userId, $record);
        $user = $this->repository->findById($userId);

        Response::ok('User updated successfully.', $user);
    }

    /**
     * DELETE /api/v1/users/{id}  (soft delete)
     */
    public function destroy(string $id): void
    {
        $userId = $this->parseId($id);

        if ($this->repository->findById($userId) === null) {
            Response::notFound("User with id {$userId} was not found.");
        }

        $this->repository->delete($userId);

        Response::ok('User deleted successfully.');
    }

    /**
     * Returns a trimmed value for the field, or the default when missing/blank.
     *
     * @param array<string,mixed> $data
     */
    private function valueOrDefault(array $data, string $field, string $default): string
    {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            return $default;
        }

        return (string) $data[$field];
    }
}
