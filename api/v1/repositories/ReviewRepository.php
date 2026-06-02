<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * ReviewRepository.php
 * Database access for the `reviews` table. Reads JOIN both the
 * `users` and `places` tables for meaningful associated data.
 *
 * Schema (reviews):
 *   id         BIGINT UNSIGNED PK
 *   user_id    BIGINT UNSIGNED NOT NULL (FK -> users.id, CASCADE)
 *   place_id   BIGINT UNSIGNED NOT NULL (FK -> places.id, CASCADE)
 *   rating     TINYINT UNSIGNED NOT NULL  (1..5)
 *   comment    TEXT    NULL
 *   created_at TIMESTAMP
 *   updated_at TIMESTAMP
 */

class ReviewRepository
{
    private PDO $db;

    private const SELECT_WITH_RELATIONS = "
        SELECT
            r.id,
            r.user_id,
            u.name AS user_name,
            r.place_id,
            p.name AS place_name,
            r.rating,
            r.comment,
            r.created_at,
            r.updated_at
        FROM reviews r
        INNER JOIN users  u ON u.id = r.user_id
        INNER JOIN places p ON p.id = r.place_id
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Paginated + searchable list, optionally filtered by user/place.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, string $search = '', ?int $userId = null, ?int $placeId = null): array
    {
        [$where, $bindings] = $this->buildFilters($search, $userId, $placeId);

        $sql = self::SELECT_WITH_RELATIONS . $where
             . " ORDER BY r.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $this->bindFilters($stmt, $bindings);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->castRows($stmt->fetchAll());
    }

    public function countAll(string $search = '', ?int $userId = null, ?int $placeId = null): int
    {
        [$where, $bindings] = $this->buildFilters($search, $userId, $placeId);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM reviews r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN places p ON p.id = r.place_id" . $where
        );
        $this->bindFilters($stmt, $bindings);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT_WITH_RELATIONS . " WHERE r.id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $this->castRow($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO reviews (user_id, place_id, rating, comment)
             VALUES (:user_id, :place_id, :rating, :comment)"
        );
        $this->bindWritableColumns($stmt, $data);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE reviews
             SET user_id = :user_id, place_id = :place_id, rating = :rating, comment = :comment
             WHERE id = :id"
        );
        $this->bindWritableColumns($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM reviews WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     */
    private function bindWritableColumns(PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':place_id', $data['place_id'], PDO::PARAM_INT);
        $stmt->bindValue(':rating', $data['rating'], PDO::PARAM_INT);

        $comment = $data['comment'] ?? null;
        $stmt->bindValue(':comment', $comment, $comment === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }

    /**
     * Builds the WHERE clause + bindings for the optional filters.
     *
     * @return array{0:string,1:array<int,array{0:string,1:mixed,2:int}>}
     */
    private function buildFilters(string $search, ?int $userId, ?int $placeId): array
    {
        $conditions = [];
        $bindings   = [];

        if ($search !== '') {
            $conditions[] = '(r.comment LIKE :search OR u.name LIKE :search OR p.name LIKE :search)';
            $bindings[]   = [':search', '%' . $search . '%', PDO::PARAM_STR];
        }

        if ($userId !== null) {
            $conditions[] = 'r.user_id = :user_id';
            $bindings[]   = [':user_id', $userId, PDO::PARAM_INT];
        }

        if ($placeId !== null) {
            $conditions[] = 'r.place_id = :place_id';
            $bindings[]   = [':place_id', $placeId, PDO::PARAM_INT];
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $bindings];
    }

    /**
     * @param array<int,array{0:string,1:mixed,2:int}> $bindings
     */
    private function bindFilters(PDOStatement $stmt, array $bindings): void
    {
        foreach ($bindings as [$param, $value, $type]) {
            $stmt->bindValue($param, $value, $type);
        }
    }

    /**
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
        $row['id']       = (int) $row['id'];
        $row['user_id']  = (int) $row['user_id'];
        $row['place_id'] = (int) $row['place_id'];
        $row['rating']   = (int) $row['rating'];

        return $row;
    }
}
