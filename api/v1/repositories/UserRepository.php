<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * UserRepository.php
 * All database access for the `users` table. Uses PDO prepared
 * statements everywhere. The `password` column is NEVER selected
 * into API output, and deletes are SOFT (deleted_at) so a user
 * row is preserved while being hidden from normal queries.
 *
 * Schema (users):
 *   id              BIGINT UNSIGNED  PK
 *   name            VARCHAR(255)     NOT NULL
 *   email           VARCHAR(255)     NOT NULL UNIQUE
 *   password        VARCHAR(255)     NOT NULL   (stored hashed)
 *   role            ENUM('user','admin')      DEFAULT 'user'
 *   profile_picture VARCHAR(255)     NULL
 *   status          ENUM('Active','Suspended') DEFAULT 'Active'
 *   created_at      TIMESTAMP
 *   updated_at      TIMESTAMP
 *   deleted_at      TIMESTAMP        NULL   (soft-delete marker)
 */

class UserRepository
{
    private PDO $db;

    /**
     * Safe column list for output. The `password` hash is deliberately
     * excluded so it is never exposed through the API.
     */
    private const PUBLIC_COLUMNS = "
        id, name, email, role, profile_picture, status, created_at, updated_at
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns a paginated, optionally searched list of active users.
     * Soft-deleted rows (deleted_at IS NOT NULL) are excluded.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, string $search = ''): array
    {
        $sql = "SELECT " . self::PUBLIC_COLUMNS . "
                FROM users
                WHERE deleted_at IS NULL";

        // Search matches name or email.
        if ($search !== '') {
            $sql .= " AND (name LIKE :search OR email LIKE :search)";
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->castRows($stmt->fetchAll());
    }

    /**
     * Counts active users (respecting the same optional search).
     */
    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE deleted_at IS NULL";

        if ($search !== '') {
            $sql .= " AND (name LIKE :search OR email LIKE :search)";
        }

        $stmt = $this->db->prepare($sql);

        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Finds a single active user by id (password excluded).
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . "
             FROM users
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $this->castRow($row) : null;
    }

    /**
     * Checks whether an email is already used by another active user.
     *
     * @param int|null $excludeId Ignore this id (used on update).
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM users WHERE email = :email AND deleted_at IS NULL";

        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);

        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Inserts a new user. The password must already be hashed.
     *
     * @param array<string,mixed> $data Pre-validated, normalized values.
     * @return int The new user id.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role, profile_picture, status)
             VALUES (:name, :email, :password, :role, :profile_picture, :status)"
        );
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':password', $data['password'], PDO::PARAM_STR);
        $stmt->bindValue(':role', $data['role'], PDO::PARAM_STR);
        $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $this->bindNullableString($stmt, ':profile_picture', $data['profile_picture'] ?? null);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * Fully updates an existing user. The password is only changed when
     * a new (already hashed) value is provided in $data['password'].
     *
     * @param array<string,mixed> $data
     * @return bool True when a row was affected.
     */
    public function update(int $id, array $data): bool
    {
        // Build the SET clause dynamically so password is optional.
        $columns = [
            'name = :name',
            'email = :email',
            'role = :role',
            'profile_picture = :profile_picture',
            'status = :status',
        ];

        if (array_key_exists('password', $data) && $data['password'] !== null) {
            $columns[] = 'password = :password';
        }

        $sql = "UPDATE users SET " . implode(', ', $columns)
             . " WHERE id = :id AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':role', $data['role'], PDO::PARAM_STR);
        $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $this->bindNullableString($stmt, ':profile_picture', $data['profile_picture'] ?? null);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if (array_key_exists('password', $data) && $data['password'] !== null) {
            $stmt->bindValue(':password', $data['password'], PDO::PARAM_STR);
        }

        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-deletes a user by stamping deleted_at with the current time.
     * The row is kept for history but hidden from all normal queries.
     *
     * @return bool True when a row was affected.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET deleted_at = CURRENT_TIMESTAMP
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------------

    /** Binds a string param as NULL when the value is null. */
    private function bindNullableString(PDOStatement $stmt, string $param, ?string $value): void
    {
        $stmt->bindValue($param, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }

    /**
     * Casts numeric fields for clean JSON output.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function castRows(array $rows): array
    {
        return array_map([$this, 'castRow'], $rows);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function castRow(array $row): array
    {
        $row['id'] = (int) $row['id'];

        return $row;
    }
}
