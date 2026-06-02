<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * WorkingHourRepository.php
 * Database access for the `working_hours` table. Reads JOIN the
 * `places` table to include the owning place's name.
 *
 * Schema (working_hours):
 *   id          BIGINT UNSIGNED PK
 *   place_id    BIGINT UNSIGNED NOT NULL (FK -> places.id, CASCADE)
 *   day_of_week TINYINT UNSIGNED NOT NULL  (0=Sunday .. 6=Saturday)
 *   open_time   TIME    NULL
 *   close_time  TIME    NULL
 *   is_closed   BOOLEAN DEFAULT FALSE
 */

class WorkingHourRepository
{
    private PDO $db;

    private const SELECT_WITH_PLACE = "
        SELECT
            wh.id,
            wh.place_id,
            p.name AS place_name,
            wh.day_of_week,
            wh.open_time,
            wh.close_time,
            wh.is_closed
        FROM working_hours wh
        INNER JOIN places p ON p.id = wh.place_id
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
            $sql .= " WHERE wh.place_id = :place_id";
        }

        $sql .= " ORDER BY wh.place_id ASC, wh.day_of_week ASC LIMIT :limit OFFSET :offset";

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
        $sql = "SELECT COUNT(*) FROM working_hours";

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
        $stmt = $this->db->prepare(self::SELECT_WITH_PLACE . " WHERE wh.id = :id LIMIT 1");
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
            "INSERT INTO working_hours (place_id, day_of_week, open_time, close_time, is_closed)
             VALUES (:place_id, :day_of_week, :open_time, :close_time, :is_closed)"
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
            "UPDATE working_hours SET
                place_id = :place_id,
                day_of_week = :day_of_week,
                open_time = :open_time,
                close_time = :close_time,
                is_closed = :is_closed
             WHERE id = :id"
        );
        $this->bindWritableColumns($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM working_hours WHERE id = :id");
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
        $stmt->bindValue(':place_id', $data['place_id'], PDO::PARAM_INT);
        $stmt->bindValue(':day_of_week', $data['day_of_week'], PDO::PARAM_INT);
        $stmt->bindValue(':is_closed', $data['is_closed'], PDO::PARAM_INT);

        // open_time / close_time are nullable TIME columns.
        $open  = $data['open_time'] ?? null;
        $close = $data['close_time'] ?? null;
        $stmt->bindValue(':open_time', $open, $open === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':close_time', $close, $close === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
        $row['id']          = (int) $row['id'];
        $row['place_id']    = (int) $row['place_id'];
        $row['day_of_week'] = (int) $row['day_of_week'];
        $row['is_closed']   = (bool) $row['is_closed'];

        return $row;
    }
}
