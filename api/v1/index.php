<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * index.php  (Front Controller / Router)
 *
 * Single entry point for the whole API. It:
 *   1. Boots shared utilities and configuration.
 *   2. Parses the incoming URL into {entity}/{id}.
 *   3. Dispatches the request to the matching controller method.
 *
 * Flow:  Request -> Router (index.php) -> Controller -> Repository
 */

declare(strict_types=1);

// --- Bootstrap shared files -------------------------------------------------
require_once __DIR__ . '/config/cors.php';        // Headers + OPTIONS handling.
require_once __DIR__ . '/config/Database.php';     // PDO connection.
require_once __DIR__ . '/utils/Response.php';      // Standardized JSON output.
require_once __DIR__ . '/utils/Validator.php';     // Input validation helper.

// BaseController must be loaded before any controller that extends it.
require_once __DIR__ . '/controllers/BaseController.php';

require_once __DIR__ . '/repositories/CategoryRepository.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/repositories/PlaceRepository.php';
require_once __DIR__ . '/controllers/PlaceController.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/repositories/AmenityRepository.php';
require_once __DIR__ . '/controllers/AmenityController.php';
require_once __DIR__ . '/repositories/PlaceImageRepository.php';
require_once __DIR__ . '/controllers/PlaceImageController.php';
require_once __DIR__ . '/repositories/PlaceContactRepository.php';
require_once __DIR__ . '/controllers/PlaceContactController.php';
require_once __DIR__ . '/repositories/WorkingHourRepository.php';
require_once __DIR__ . '/controllers/WorkingHourController.php';
require_once __DIR__ . '/repositories/FavoriteRepository.php';
require_once __DIR__ . '/controllers/FavoriteController.php';
require_once __DIR__ . '/repositories/ReviewRepository.php';
require_once __DIR__ . '/controllers/ReviewController.php';
require_once __DIR__ . '/repositories/PlaceAmenityRepository.php';
require_once __DIR__ . '/controllers/PlaceAmenityController.php';

// --- Parse the request URI --------------------------------------------------
// Strip the query string (e.g. ?page=1) and decode the path.
$requestUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$requestUri  = rawurldecode($requestUri);

// Everything is namespaced under /api/v1/. Isolate the part after it so the
// API works no matter which sub-folder the project is deployed in.
$marker = '/api/v1';
$pos    = strpos($requestUri, $marker);
$path   = $pos !== false ? substr($requestUri, $pos + strlen($marker)) : $requestUri;

// Split "categories/5" -> ["categories", "5"].
$segments = array_values(array_filter(explode('/', trim($path, '/')), static fn($s) => $s !== ''));

$entity = $segments[0] ?? null;             // e.g. "categories"
$id     = isset($segments[1]) ? $segments[1] : null;  // e.g. "5"
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Global safety net ------------------------------------------------------
// Any uncaught database/runtime error becomes a clean 500 JSON response
// instead of leaking a stack trace to the client.
try {
    // Root of the API (no entity) -> friendly welcome message.
    if ($entity === null) {
        Response::ok('Welcome to the DEVMMND Tourism API v1.', [
            'version'   => 'v1',
            'endpoints' => [
                'categories', 'places', 'users', 'amenities',
                'place-images', 'place-contacts', 'working-hours',
                'favorites', 'reviews', 'place-amenities',
            ],
        ]);
    }

    // --- Route table: map {entity} to its controller -----------------------
    switch ($entity) {
        case 'categories':
            $controller = new CategoryController();
            break;

        case 'places':
            $controller = new PlaceController();
            break;

        case 'users':
            $controller = new UserController();
            break;

        case 'amenities':
            $controller = new AmenityController();
            break;

        case 'place-images':
            $controller = new PlaceImageController();
            break;

        case 'place-contacts':
            $controller = new PlaceContactController();
            break;

        case 'working-hours':
            $controller = new WorkingHourController();
            break;

        case 'favorites':
            $controller = new FavoriteController();
            break;

        case 'reviews':
            $controller = new ReviewController();
            break;

        case 'place-amenities':
            $controller = new PlaceAmenityController();
            break;

        default:
            Response::notFound("The requested resource '{$entity}' was not found.");
    }

    // --- Dispatch based on HTTP method + presence of an id -----------------
    switch ($method) {
        case 'GET':
            // GET /entity/{id}  -> single record
            // GET /entity       -> list with pagination + search
            $id !== null ? $controller->show($id) : $controller->index();
            break;

        case 'POST':
            // POST /entity -> create (an id in the URL is invalid here)
            if ($id !== null) {
                Response::badRequest('A new resource cannot be created on a specific id.');
            }
            $controller->store();
            break;

        case 'PUT':
            // PUT /entity/{id} -> full update (id is mandatory)
            if ($id === null) {
                Response::badRequest('An id is required to update a resource.');
            }
            $controller->update($id);
            break;

        case 'DELETE':
            // DELETE /entity/{id} -> delete (id is mandatory)
            if ($id === null) {
                Response::badRequest('An id is required to delete a resource.');
            }
            $controller->destroy($id);
            break;

        default:
            Response::methodNotAllowed("HTTP method '{$method}' is not allowed.");
    }
} catch (Throwable $e) {
    // Log the real error server-side; never expose internals to the client.
    error_log('[Tourism API] ' . $e->getMessage());
    Response::serverError('An unexpected server error occurred. Please try again later.');
}
