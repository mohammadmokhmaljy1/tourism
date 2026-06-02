<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceAmenityRepository.php
 * Database access for the `place_amenities` junction (many-to-many
 * link between places and amenities). It has a COMPOSITE primary key
 * (place_id, amenity_id) and no surrogate id. Reads JOIN both parent
 * tables to return meaningful place/amenity details.
 *
 * Schema (place_amenities):
 *   place_id   BIGINT UNSIGNED NOT NULL (FK -> places.id, CASCADE)
 *   amenity_id BIGINT UNSIGNED NOT NULL (FK -> amenities.id, CASCADE)
 *   PRIMARY KEY (place_id, amenity_id)
 */

class PlaceAmenityRepository
{
    private PDO $db;

    private const SELECT_WITH_RELATIONS = "
        SELECT
            pa.place_id,
            p.name AS place_name,
            pa.amenity_id,
            a.name AS amenity_name,
            a.icon AS amenity_icon
        FROM place_amenities pa
        INNER JOIN places    p ON p.id = pa.place_id
        INNER JOIN amenities a ON a.id = pa.amenity_id
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Paginated list of links, optionally filtered by place or amenity.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(int $limit, int $offset, ?int $placeId = null, ?int $amenityId = null): array
    {
        [$where, $bindings] = $this->buildFilters($placeId, $amenityId);

        $sql = self::SELECT_WITH_RELATIONS . $where
             . " ORDER BY pa.place_id ASC, pa.amenity_id ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($bindings as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->castRows($stmt->fetchAll());
    }

    public function countAll(?int $placeId = null, ?int $amenityId = null): int
    {
        [$where, $bindings] = $this->buildFilters($placeId, $amenityId);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM place_amenities pa" . $where);

        foreach ($bindings as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Returns all amenities linked to a single place.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findByPlace(int $placeId): array
    {
        $stmt = $this->db->prepare(
            self::SELECT_WITH_RELATIONS . " WHERE pa.place_id = :place_id ORDER BY a.name ASC"
        );
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->castRows($stmt->fetchAll());
    }

    /** Checks whether a specific place/amenity link already exists. */
    public function exists(int $placeId, int $amenityId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM place_amenities
             WHERE place_id = :place_id AND amenity_id = :amenity_id
             LIMIT 1"
        );
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $stmt->bindValue(':amenity_id', $amenityId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** Attaches an amenity to a place. */
    public function attach(int $placeId, int $amenityId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO place_amenities (place_id, amenity_id)
             VALUES (:place_id, :amenity_id)"
        );
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $stmt->bindValue(':amenity_id', $amenityId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Detaches an amenity from a place. Returns true when a row was removed. */
    public function detach(int $placeId, int $amenityId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM place_amenities
             WHERE place_id = :place_id AND amenity_id = :amenity_id"
        );
        $stmt->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $stmt->bindValue(':amenity_id', $amenityId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------

    /**
     * @return array{0:string,1:array<string,int>}
     */
    private function buildFilters(?int $placeId, ?int $amenityId): array
    {
        $conditions = [];
        $bindings   = [];

        if ($placeId !== null) {
            $conditions[]            = 'pa.place_id = :place_id';
            $bindings[':place_id']   = $placeId;
        }

        if ($amenityId !== null) {
            $conditions[]            = 'pa.amenity_id = :amenity_id';
            $bindings[':amenity_id'] = $amenityId;
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
        return array_map(static function (array $row): array {
            $row['place_id']   = (int) $row['place_id'];
            $row['amenity_id'] = (int) $row['amenity_id'];
            return $row;
        }, $rows);
    }
}
