<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * BaseController.php
 * Shared HTTP / validation / pagination plumbing reused by every
 * resource controller. Keeping it here removes duplication and
 * guarantees all endpoints behave identically (same id parsing,
 * same JSON body handling, same pagination shape).
 */

abstract class BaseController
{
    /**
     * Validates the {id} route segment and returns it as a positive int.
     * Sends a 400 response and stops on an invalid id.
     */
    protected function parseId(string $id): int
    {
        if (!ctype_digit($id) || (int) $id <= 0) {
            Response::badRequest('The id must be a positive integer.');
        }

        return (int) $id;
    }

    /**
     * Reads and decodes the raw JSON request body.
     * Sends a 400 response and stops on malformed/empty JSON.
     *
     * @return array<string,mixed>
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            Response::badRequest('Request body is empty. A JSON payload is required.');
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Response::badRequest('Invalid JSON payload: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Reads & sanitizes the common list query params.
     *
     * @return array{page:int,limit:int,offset:int,search:string}
     */
    protected function paginationParams(): array
    {
        $page   = isset($_GET['page'])  ? max(1, (int) $_GET['page'])            : 1;
        $limit  = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 10;
        $search = isset($_GET['search']) ? trim((string) $_GET['search'])        : '';

        return [
            'page'   => $page,
            'limit'  => $limit,
            'offset' => ($page - 1) * $limit,
            'search' => $search,
        ];
    }

    /**
     * Builds the standard pagination metadata block.
     *
     * @return array<string,int>
     */
    protected function buildPagination(int $total, int $page, int $limit): array
    {
        return [
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page,
            'last_page'    => $limit > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    /**
     * Trimmed string for an optional field, or NULL when missing/blank
     * (maps cleanly to a nullable column).
     *
     * @param array<string,mixed> $data
     */
    protected function optionalString(array $data, string $field): ?string
    {
        if (!isset($data[$field])) {
            return null;
        }

        $value = trim((string) $data[$field]);

        return $value === '' ? null : $value;
    }

    /**
     * Integer for an optional field, or NULL when missing/blank.
     *
     * @param array<string,mixed> $data
     */
    protected function optionalInt(array $data, string $field): ?int
    {
        if (!isset($data[$field]) || $data[$field] === '') {
            return null;
        }

        return (int) $data[$field];
    }

    /**
     * Normalizes a "boolean-ish" input (true/false, 1/0, "true"/"false")
     * into an int 0/1 suitable for a TINYINT/BOOLEAN column.
     *
     * @param array<string,mixed> $data
     */
    protected function boolToInt(array $data, string $field, bool $default = false): int
    {
        if (!isset($data[$field]) || $data[$field] === '') {
            return $default ? 1 : 0;
        }

        return filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
}
