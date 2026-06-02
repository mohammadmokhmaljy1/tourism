<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceAmenityController.php
 * HTTP/validation layer for the `place_amenities` junction table.
 *
 * Because this table has a COMPOSITE key (place_id, amenity_id) and
 * no single id, the REST surface is adapted accordingly:
 *
 *   GET    /api/v1/place-amenities                      -> index  (optional ?place_id / ?amenity_id)
 *   GET    /api/v1/place-amenities/{place_id}           -> show    (all amenities of one place)
 *   POST   /api/v1/place-amenities                      -> store   (attach: body {place_id, amenity_id})
 *   DELETE /api/v1/place-amenities/{place_id}?amenity_id=Y -> destroy (detach)
 *   PUT    -> not supported (a pure link has no editable columns)
 */

class PlaceAmenityController extends BaseController
{
    private PlaceAmenityRepository $repository;
    private PlaceRepository $placeRepository;
    private AmenityRepository $amenityRepository;

    public function __construct()
    {
        $this->repository        = new PlaceAmenityRepository();
        $this->placeRepository   = new PlaceRepository();
        $this->amenityRepository = new AmenityRepository();
    }

    public function index(): void
    {
        $params    = $this->paginationParams();
        $placeId   = isset($_GET['place_id'])   && $_GET['place_id']   !== '' ? max(1, (int) $_GET['place_id'])   : null;
        $amenityId = isset($_GET['amenity_id']) && $_GET['amenity_id'] !== '' ? max(1, (int) $_GET['amenity_id']) : null;

        $links = $this->repository->getAll($params['limit'], $params['offset'], $placeId, $amenityId);
        $total = $this->repository->countAll($placeId, $amenityId);

        Response::ok('Place amenities retrieved successfully.', [
            'items'      => $links,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    /**
     * The {id} segment is interpreted as a place id: return every amenity
     * linked to that place.
     */
    public function show(string $id): void
    {
        $placeId = $this->parseId($id);

        if ($this->placeRepository->findById($placeId) === null) {
            Response::notFound("Place with id {$placeId} was not found.");
        }

        $links = $this->repository->findByPlace($placeId);

        Response::ok('Place amenities retrieved successfully.', $links);
    }

    public function store(): void
    {
        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('place_id')
            ->positiveInteger('place_id')
            ->required('amenity_id')
            ->positiveInteger('amenity_id');

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $placeId   = (int) $data['place_id'];
        $amenityId = (int) $data['amenity_id'];

        // Both foreign keys are NOT NULL: confirm each parent exists.
        $errors = [];
        if ($this->placeRepository->findById($placeId) === null) {
            $errors['place_id'] = "Place with id {$placeId} does not exist.";
        }
        if ($this->amenityRepository->findById($amenityId) === null) {
            $errors['amenity_id'] = "Amenity with id {$amenityId} does not exist.";
        }
        if ($errors) {
            Response::unprocessable('Validation failed.', $errors);
        }

        // The composite primary key forbids duplicate links.
        if ($this->repository->exists($placeId, $amenityId)) {
            Response::unprocessable('Validation failed.', [
                'amenity_id' => 'This amenity is already attached to the place.',
            ]);
        }

        $this->repository->attach($placeId, $amenityId);

        Response::created('Amenity attached to place successfully.', [
            'place_id'   => $placeId,
            'amenity_id' => $amenityId,
        ]);
    }

    /**
     * PUT is meaningless for a pure junction row (nothing to update).
     */
    public function update(string $id): void
    {
        Response::methodNotAllowed(
            'Updating a place-amenity link is not supported. Detach and attach instead.'
        );
    }

    /**
     * DELETE /api/v1/place-amenities/{place_id}?amenity_id=Y
     * The {id} segment is the place id; amenity_id comes from the query.
     */
    public function destroy(string $id): void
    {
        $placeId = $this->parseId($id);

        if (!isset($_GET['amenity_id']) || $_GET['amenity_id'] === '' || !ctype_digit((string) $_GET['amenity_id'])) {
            Response::badRequest('A valid amenity_id query parameter is required to detach.');
        }

        $amenityId = (int) $_GET['amenity_id'];

        if (!$this->repository->exists($placeId, $amenityId)) {
            Response::notFound('No link exists between the given place and amenity.');
        }

        $this->repository->detach($placeId, $amenityId);

        Response::ok('Amenity detached from place successfully.');
    }
}
