<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * cors.php
 * Sends the CORS headers and the standard JSON content type that
 * every API response must use. It also short-circuits the browser
 * pre-flight (OPTIONS) request.
 */

// Every response in this API is JSON encoded as UTF-8.
header('Content-Type: application/json; charset=UTF-8');

// Allow cross-origin access (open API). Restrict the origin in production.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Browsers send an OPTIONS pre-flight before the real request.
// There is nothing to process for it, so we answer 200 and stop.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}
