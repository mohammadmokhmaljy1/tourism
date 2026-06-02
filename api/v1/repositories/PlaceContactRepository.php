<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceContactRepository.php
 * Database access for the `place_contacts` table. Reads JOIN the
 * `places` table to include the owning place's name.
 *
 * Schema (place_contacts):
 *   id            BIGINT UNSIGNED PK
 *   place_id      BIGINT UNSIGNED NOT NULL (FK -> places.id, CASCADE)
 *   platform      ENUM('phone','whatsapp','instagram','website') NOT NULL
 *   contact_value VARCHAR(255)    NOT NULL
 */

class PlaceContactRepository
{
    private PDO $db;

    private const SELECT_WITH_PLACE = "
        SELECT
            pc.id,
            pc.place_id,
            p.name AS place_name,
            pc.platform,
            pc.contact_value
        FROM place_contacts pc
        INNER JOIN places p ON p.id = pc.place_id
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, ?int $placeId = null): array
    {
        $sql = self::SELECT_WITH_PLACE;

        if ($placeId !== null) {
            $sql .= " WHERE pc.place_id = :place_id";
        }

        $sql .= " ORDER BY pc.id ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        if ($placeId !== null) {
            $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->castRows($stmt->fetchAll());
    }

    public function countAll(?int $placeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM place_contacts";

        if ($placeId !== null) {
            $sql .= " WHERE place_id = :place_id";
        }

        $stmt = $this->db->prepare($sql);

        if ($placeId !== null) {
            $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT_WITH_PLACE . " WHERE pc.id = :id LIMIT 1");
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
            "INSERT INTO place_contacts (place_id, platform, contact_value)
             VALUES (:place_id, :platform, :contact_value)"
        );
        $stmt->bindValue(':place_id', $data['place_id'], PDO::PARAM_INT);
        $stmt->bindValue(':platform', $data['platform'], PDO::PARAM_STR);
        $stmt->bindValue(':contact_value', $data['contact_value'], PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE place_contacts
             SET place_id = :place_id, platform = :platform, contact_value = :contact_value
             WHERE id = :id"
        );
        $stmt->bindValue(':place_id', $data['place_id'], PDO::PARAM_INT);
        $stmt->bindValue(':platform', $data['platform'], PDO::PARAM_STR);
        $stmt->bindValue(':contact_value', $data['contact_value'], PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM place_contacts WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------

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
        $row['place_id'] = (int) $row['place_id'];

        return $row;
    }
}
