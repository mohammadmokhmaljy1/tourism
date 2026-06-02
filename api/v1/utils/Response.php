<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * Response.php
 * Centralizes every JSON response so the whole API speaks the
 * exact same structure and uses correct HTTP status codes.
 *
 * Standard envelope:
 * {
 *   "status":  200,
 *   "success": true,
 *   "message": "Descriptive message",
 *   "data":    { ... } | [ ... ] | null
 * }
 */

class Response
{
    /**
     * Sends a JSON response and terminates the script.
     *
     * @param int    $status  HTTP status code (e.g. 200, 404, 422).
     * @param bool   $success Whether the operation succeeded.
     * @param string $message Human-readable description.
     * @param mixed  $data    Payload (object, array or null).
     * @return void
     */
    public static function send(int $status, bool $success, string $message, $data = null): void
    {
        // Make sure the correct HTTP status reaches the client.
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'status'  => $status,
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Stop further execution; the response is complete.
        exit;
    }

    /** 200 OK - Successful read / update / delete. */
    public static function ok(string $message, $data = null): void
    {
        self::send(200, true, $message, $data);
    }

    /** 201 Created - A new resource was successfully created. */
    public static function created(string $message, $data = null): void
    {
        self::send(201, true, $message, $data);
    }

    /** 400 Bad Request - Malformed request (e.g. invalid JSON, bad id). */
    public static function badRequest(string $message, $data = null): void
    {
        self::send(400, false, $message, $data);
    }

    /** 404 Not Found - The requested resource does not exist. */
    public static function notFound(string $message, $data = null): void
    {
        self::send(404, false, $message, $data);
    }

    /** 405 Method Not Allowed - HTTP verb not supported on this route. */
    public static function methodNotAllowed(string $message, $data = null): void
    {
        self::send(405, false, $message, $data);
    }

    /** 422 Unprocessable Entity - Validation failed. */
    public static function unprocessable(string $message, $data = null): void
    {
        self::send(422, false, $message, $data);
    }

    /** 500 Internal Server Error - Unexpected server/database failure. */
    public static function serverError(string $message, $data = null): void
    {
        self::send(500, false, $message, $data);
    }
}
