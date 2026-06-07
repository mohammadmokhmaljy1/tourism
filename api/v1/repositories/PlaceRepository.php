<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceRepository.php
 * All database access for the `places` table. Every read JOINs the
 * `categories` table so each place is returned together with its
 * category name and icon. All queries use PDO prepared statements.
 *
 * Schema (places):
 *   id              BIGINT UNSIGNED  PK
 *   name            VARCHAR(255)     NOT NULL
 *   description     TEXT             NULL
 *   category_id     BIGINT UNSIGNED  NOT NULL (FK -> categories.id)
 *   address         VARCHAR(255)     NULL
 *   Maps_url TEXT             NULL
 *   average_rating  DECIMAL(3,2)     DEFAULT 0.00
 *   reviews_count   INT UNSIGNED     DEFAULT 0
 *   price_level     TINYINT UNSIGNED NULL  (1..5)
 *   status          ENUM('Open','Temporarily Closed','Closed') DEFAULT 'Open'
 *   created_at      TIMESTAMP
 *   updated_at      TIMESTAMP
 */

class PlaceRepository
{
    private PDO $db;

    /**
     * Reusable SELECT clause. The JOIN enriches every place with its
     * related category data (category_name, category_icon).
     */
    private const SELECT_WITH_CATEGORY = "
        SELECT
            p.id,
            p.name,
            p.description,
            p.category_id,
            c.name  AS category_name,
            c.icon  AS category_icon,
            p.address,
            p.Maps_url,
            p.average_rating,
            p.reviews_count,
            p.price_level,
            p.status,
            p.created_at,
            p.updated_at
        FROM places p
        INNER JOIN categories c ON c.id = p.category_id
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns a paginated, optionally searched list of places, each one
     * joined with its category details.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, string $search = ''): array
    {
        $sql = self::SELECT_WITH_CATEGORY;

        // Search matches the place name, address, or category name.
        if ($search !== '') {
            $sql .= " WHERE p.name LIKE :search
                       OR p.address LIKE :search
                       OR c.name LIKE :search";
        }

        $sql .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

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
     * Counts the total places (respecting the same optional search),
     * for pagination metadata.
     */
    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*)
                FROM places p
                INNER JOIN categories c ON c.id = p.category_id";

        if ($search !== '') {
            $sql .= " WHERE p.name LIKE :search
                       OR p.address LIKE :search
                       OR c.name LIKE :search";
        }

        $stmt = $this->db->prepare($sql);

        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Finds a single place (joined with its category) by id.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql  = self::SELECT_WITH_CATEGORY . " WHERE p.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $this->castRow($row) : null;
    }

    /**
     * Inserts a new place. Optional/nullable fields are stored as NULL
     * when not provided; fields with DB defaults are left untouched.
     *
     * @param array<string,mixed> $data Pre-validated, normalized values.
     * @return int The new place id.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO places
                    (name, description, category_id, address, Maps_url, price_level, status)
                VALUES
                    (:name, :description, :category_id, :address, :Maps_url, :price_level, :status)";

        $stmt = $this->db->prepare($sql);
        $this->bindWritableColumns($stmt, $data);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * Fully updates an existing place.
     *
     * @param array<string,mixed> $data Pre-validated, normalized values.
     * @return bool True when a row was affected.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE places SET
                    name            = :name,
                    description     = :description,
                    category_id     = :category_id,
                    address         = :address,
                    Maps_url = :Maps_url,
                    price_level     = :price_level,
                    status          = :status
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $this->bindWritableColumns($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Deletes a place by id. Related rows (images, contacts, etc.) are
     * removed automatically via ON DELETE CASCADE.
     *
     * @return bool True when a row was deleted.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM places WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------------

    /**
     * Binds the user-writable columns shared by INSERT and UPDATE,
     * honouring the nullable columns in the schema.
     *
     * @param array<string,mixed> $data
     */
    private function bindWritableColumns(PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':category_id', $data['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':status', $data['status'], PDO::PARAM_STR);

        // Nullable text columns.
        $this->bindNullableString($stmt, ':description', $data['description'] ?? null);
        $this->bindNullableString($stmt, ':address', $data['address'] ?? null);
        $this->bindNullableString($stmt, ':Maps_url', $data['Maps_url'] ?? null);

        // Nullable integer column (price_level, 1..5).
        $priceLevel = $data['price_level'] ?? null;
        if ($priceLevel === null) {
            $stmt->bindValue(':price_level', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':price_level', (int) $priceLevel, PDO::PARAM_INT);
        }
    }

    /** Binds a string param as NULL when the value is null. */
    private function bindNullableString(PDOStatement $stmt, string $param, ?string $value): void
    {
        $stmt->bindValue($param, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }

    /**
     * Casts numeric DB strings to proper PHP types for clean JSON output.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function castRows(array $rows): array
    {
        return array_map([$this, 'castRow'], $rows);
    }

    /**
     * Casts a single row's numeric fields.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function castRow(array $row): array
    {
        $row['id']            = (int) $row['id'];
        $row['category_id']   = (int) $row['category_id'];
        $row['reviews_count'] = (int) $row['reviews_count'];
        $row['average_rating'] = (float) $row['average_rating'];
        $row['price_level']   = $row['price_level'] !== null ? (int) $row['price_level'] : null;

        return $row;
    }
}
