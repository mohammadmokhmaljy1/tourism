<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * FavoriteRepository.php
 * Database access for the `favorites` table. Reads JOIN both the
 * `users` and `places` tables for meaningful associated data.
 *
 * Schema (favorites):
 *   id         BIGINT UNSIGNED PK
 *   user_id    BIGINT UNSIGNED NOT NULL (FK -> users.id, CASCADE)
 *   place_id   BIGINT UNSIGNED NOT NULL (FK -> places.id, CASCADE)
 *   created_at TIMESTAMP
 *   updated_at TIMESTAMP
 *   UNIQUE (user_id, place_id)
 */

class FavoriteRepository
{
    private PDO $db;

    private const SELECT_WITH_RELATIONS = "
        SELECT
            f.id,
            f.user_id,
            u.name AS user_name,
            f.place_id,
            p.name AS place_name,
            f.created_at,
            f.updated_at
        FROM favorites f
        INNER JOIN users  u ON u.id = f.user_id
        INNER JOIN places p ON p.id = f.place_id
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Paginated list, optionally filtered by user or by place.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, ?int $userId = null, ?int $placeId = null): array
    {
        [$where, $bindings] = $this->buildFilters($userId, $placeId);

        $sql = self::SELECT_WITH_RELATIONS . $where
             . " ORDER BY f.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($bindings as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->castRows($stmt->fetchAll());
    }

    public function countAll(?int $userId = null, ?int $placeId = null): int
    {
        [$where, $bindings] = $this->buildFilters($userId, $placeId);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM favorites f" . $where);

        foreach ($bindings as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT_WITH_RELATIONS . " WHERE f.id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $this->castRow($row) : null;
    }

    /**
     * Checks whether a (user_id, place_id) favorite already exists,
     * optionally ignoring a given id (used on update).
     */
    public function pairExists(int $userId, int $placeId, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM favorites WHERE user_id = :user_id AND place_id = :place_id";

        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);

        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    public function create(int $userId, int $placeId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO favorites (user_id, place_id) VALUES (:user_id, :place_id)"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, int $placeId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE favorites SET user_id = :user_id, place_id = :place_id WHERE id = :id"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM favorites WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------

    /**
     * Builds an optional WHERE clause + its integer bindings.
     *
     * @return array{0:string,1:array<string,int>}
     */
    private function buildFilters(?int $userId, ?int $placeId): array
    {
        $conditions = [];
        $bindings   = [];

        if ($userId !== null) {
            $conditions[]          = 'f.user_id = :user_id';
            $bindings[':user_id']  = $userId;
        }

        if ($placeId !== null) {
            $conditions[]          = 'f.place_id = :place_id';
            $bindings[':place_id'] = $placeId;
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $bindings];
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

        return $row;
    }
}
