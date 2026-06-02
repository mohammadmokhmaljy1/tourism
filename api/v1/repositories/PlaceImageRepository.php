<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceImageRepository.php
 * Database access for the `place_images` table. Reads JOIN the
 * `places` table to include the owning place's name.
 *
 * Schema (place_images):
 *   id         BIGINT UNSIGNED PK
 *   place_id   BIGINT UNSIGNED NOT NULL (FK -> places.id, CASCADE)
 *   image_url  VARCHAR(255)    NOT NULL
 *   is_primary BOOLEAN         DEFAULT FALSE
 */

class PlaceImageRepository
{
    private PDO $db;

    private const SELECT_WITH_PLACE = "
        SELECT
            pi.id,
            pi.place_id,
            p.name AS place_name,
            pi.image_url,
            pi.is_primary
        FROM place_images pi
        INNER JOIN places p ON p.id = pi.place_id
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Paginated list, optionally filtered to a single place.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, ?int $placeId = null): array
    {
        $sql = self::SELECT_WITH_PLACE;

        if ($placeId !== null) {
            $sql .= " WHERE pi.place_id = :place_id";
        }

        $sql .= " ORDER BY pi.is_primary DESC, pi.id ASC LIMIT :limit OFFSET :offset";

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
        $sql = "SELECT COUNT(*) FROM place_images";

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
        $stmt = $this->db->prepare(self::SELECT_WITH_PLACE . " WHERE pi.id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row !== false ? $this->castRow($row) : null;
    }

    /**
     * Inserts an image. When marked primary, all other images of the same
     * place are demoted first (only one primary per place), inside a transaction.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $this->db->beginTransaction();
        try {
            if ((int) $data['is_primary'] === 1) {
                $this->clearPrimary((int) $data['place_id']);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO place_images (place_id, image_url, is_primary)
                 VALUES (:place_id, :image_url, :is_primary)"
            );
            $stmt->bindValue(':place_id', $data['place_id'], PDO::PARAM_INT);
            $stmt->bindValue(':image_url', $data['image_url'], PDO::PARAM_STR);
            $stmt->bindValue(':is_primary', $data['is_primary'], PDO::PARAM_INT);
            $stmt->execute();

            $id = (int) $this->db->lastInsertId();
            $this->db->commit();

            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            if ((int) $data['is_primary'] === 1) {
                $this->clearPrimary((int) $data['place_id'], $id);
            }

            $stmt = $this->db->prepare(
                "UPDATE place_images
                 SET place_id = :place_id, image_url = :image_url, is_primary = :is_primary
                 WHERE id = :id"
            );
            $stmt->bindValue(':place_id', $data['place_id'], PDO::PARAM_INT);
            $stmt->bindValue(':image_url', $data['image_url'], PDO::PARAM_STR);
            $stmt->bindValue(':is_primary', $data['is_primary'], PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $affected = $stmt->rowCount();

            $this->db->commit();

            return $affected > 0;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM place_images WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------

    /** Demotes every primary image of a place (optionally excluding one id). */
    private function clearPrimary(int $placeId, ?int $exceptId = null): void
    {
        $sql = "UPDATE place_images SET is_primary = 0 WHERE place_id = :place_id";

        if ($exceptId !== null) {
            $sql .= " AND id <> :except_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);

        if ($exceptId !== null) {
            $stmt->bindValue(':except_id', $exceptId, PDO::PARAM_INT);
        }

        $stmt->execute();
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
        $row['id']         = (int) $row['id'];
        $row['place_id']   = (int) $row['place_id'];
        $row['is_primary'] = (bool) $row['is_primary'];

        return $row;
    }
}
