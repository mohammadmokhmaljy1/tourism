<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * RevokedTokenRepository.php
 * Stores revoked JWT ids (jti) so logged-out tokens cannot be reused.
 */

class RevokedTokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Returns true when the token id has been revoked. */
    public function isRevoked(string $jti): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM revoked_tokens WHERE jti = :jti LIMIT 1'
        );
        $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** Records a token id as revoked until its original expiry time. */
    public function revoke(string $jti, int $expiresAtUnix): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO revoked_tokens (jti, expires_at)
             VALUES (:jti, FROM_UNIXTIME(:expires_at))'
        );
        $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAtUnix, PDO::PARAM_INT);
        $stmt->execute();
    }
}
