<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * Database.php
 * Handles a single, secure PDO connection to the MySQL database.
 *
 * The connection is created lazily (only once) and reused across
 * the request lifecycle via a static instance (Singleton style).
 */

class Database
{
    // --- Connection credentials (adjust to match your environment) ---
    private const HOST    = '127.0.0.1';
    private const PORT    = '3306';
    private const DB_NAME = 'u696050269_tourism_db';
    private const USER    = 'u696050269_tourism_user';
    private const PASS    = 'O8|yq=NMl7s|';
    private const CHARSET = 'utf8mb4';

    /**
     * Holds the single shared PDO instance for the current request.
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * Returns the shared PDO connection, creating it on first use.
     *
     * @return PDO
     * @throws PDOException When the connection cannot be established.
     */
    public static function getConnection(): PDO
    {
        // Reuse the existing connection if it was already opened.
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        // Data Source Name describing how to reach the database.
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::HOST,
            self::PORT,
            self::DB_NAME,
            self::CHARSET
        );

        // Secure and predictable PDO behaviour.
        $options = [
            // Throw exceptions on errors so we can handle them centrally.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements (extra protection vs SQL injection).
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        self::$instance = new PDO($dsn, self::USER, self::PASS, $options);

        return self::$instance;
    }

    // Prevent instantiation; this class is used statically only.
    private function __construct() {}
    private function __clone() {}
}
