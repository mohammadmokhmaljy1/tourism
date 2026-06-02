<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * CategoryRepository.php
 * All database access for the `categories` table lives here.
 * Every query uses PDO prepared statements to prevent SQL injection.
 *
 * Schema (categories):
 *   id         BIGINT UNSIGNED  PK
 *   name       VARCHAR(255)     NOT NULL
 *   icon       VARCHAR(255)     NULL
 *   created_at TIMESTAMP
 *   updated_at TIMESTAMP
 */

class CategoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns a paginated, optionally searched list of categories.
     *
     * @param int    $limit  Records per page.
     * @param int    $offset Records to skip.
     * @param string $search Optional keyword to match against the name.
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, string $search = ''): array
    {
        $sql = "SELECT id, name, icon, created_at, updated_at
                FROM categories";

        // Add the search filter only when a keyword was supplied.
        if ($search !== '') {
            $sql .= " WHERE name LIKE :search";
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        // LIMIT/OFFSET must be bound as integers.
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Counts the total categories (respecting the same optional search),
     * used to build pagination metadata.
     */
    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM categories";

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
     * Finds a single category by id.
     *
     * @return array<string,mixed>|null  The row, or null when not found.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, icon, created_at, updated_at
             FROM categories
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Inserts a new category and returns its generated id.
     *
     * @param string      $name Required category name.
     * @param string|null $icon Optional icon (stored as NULL when absent).
     */
    public function create(string $name, ?string $icon): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO categories (name, icon)
             VALUES (:name, :icon)"
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        // Respect the nullable `icon` column.
        $stmt->bindValue(':icon', $icon, $icon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * Fully updates an existing category.
     *
     * @return bool True when a row was affected.
     */
    public function update(int $id, string $name, ?string $icon): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE categories
             SET name = :name, icon = :icon
             WHERE id = :id"
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':icon', $icon, $icon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Deletes a category by id.
     *
     * @return bool True when a row was deleted.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Counts how many places reference this category.
     * Used to give a clear error before a RESTRICTed delete fails.
     */
    public function countLinkedPlaces(int $id): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM places WHERE category_id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
