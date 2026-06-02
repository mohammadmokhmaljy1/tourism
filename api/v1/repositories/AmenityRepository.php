<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * AmenityRepository.php
 * Database access for the `amenities` table (PDO prepared statements).
 *
 * Schema (amenities):
 *   id         BIGINT UNSIGNED PK
 *   name       VARCHAR(255)    NOT NULL
 *   icon       VARCHAR(255)    NULL
 *   created_at TIMESTAMP
 */

class AmenityRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Paginated, optionally searched list of amenities.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, string $search = ''): array
    {
        $sql = "SELECT id, name, icon, created_at FROM amenities";

        if ($search !== '') {
            $sql .= " WHERE name LIKE :search";
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM amenities";

        if ($search !== '') {
            $sql .= " WHERE name LIKE :search";
        }

        $stmt = $this->db->prepare($sql);

        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, icon, created_at FROM amenities WHERE id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function create(string $name, ?string $icon): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO amenities (name, icon) VALUES (:name, :icon)"
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':icon', $icon, $icon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $icon): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE amenities SET name = :name, icon = :icon WHERE id = :id"
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':icon', $icon, $icon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        // Links in place_amenities are removed automatically (ON DELETE CASCADE).
        $stmt = $this->db->prepare("DELETE FROM amenities WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
