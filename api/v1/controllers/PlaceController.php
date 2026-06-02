<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceController.php
 * Handles HTTP request/response and validation for the `places`
 * resource. All database work is delegated to PlaceRepository,
 * which JOINs category details into every place.
 *
 * Endpoints:
 *   GET    /api/v1/places          -> index   (paginate + search)
 *   GET    /api/v1/places/{id}     -> show
 *   POST   /api/v1/places          -> store
 *   PUT    /api/v1/places/{id}     -> update
 *   DELETE /api/v1/places/{id}     -> destroy
 */

class PlaceController extends BaseController
{
    private PlaceRepository $repository;
    private CategoryRepository $categoryRepository;

    /** Allowed values for the `status` ENUM column. */
    private const STATUSES = ['Open', 'Temporarily Closed', 'Closed'];

    public function __construct()
    {
        $this->repository         = new PlaceRepository();
        $this->categoryRepository = new CategoryRepository();
    }

    /**
     * GET /api/v1/places
     * Paginated + searchable list, each item joined with its category.
     */
    public function index(): void
    {
        $params = $this->paginationParams();

        $places = $this->repository->getAll($params['limit'], $params['offset'], $params['search']);
        $total  = $this->repository->countAll($params['search']);

        Response::ok('Places retrieved successfully.', [
            'items'      => $places,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    /**
     * GET /api/v1/places/{id}
     */
    public function show(string $id): void
    {
        $placeId = $this->parseId($id);

        $place = $this->repository->findById($placeId);

        if ($place === null) {
            Response::notFound("Place with id {$placeId} was not found.");
        }

        Response::ok('Place retrieved successfully.', $place);
    }

    /**
     * POST /api/v1/places
     */
    public function store(): void
    {
        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $newId = $this->repository->create($validated);
        $place = $this->repository->findById($newId);

        Response::created('Place created successfully.', $place);
    }

    /**
     * PUT /api/v1/places/{id}
     */
    public function update(string $id): void
    {
        $placeId = $this->parseId($id);

        if ($this->repository->findById($placeId) === null) {
            Response::notFound("Place with id {$placeId} was not found.");
        }

        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $this->repository->update($placeId, $validated);
        $place = $this->repository->findById($placeId);

        Response::ok('Place updated successfully.', $place);
    }

    /**
     * DELETE /api/v1/places/{id}
     */
    public function destroy(string $id): void
    {
        $placeId = $this->parseId($id);

        if ($this->repository->findById($placeId) === null) {
            Response::notFound("Place with id {$placeId} was not found.");
        }

        $this->repository->delete($placeId);

        Response::ok('Place deleted successfully.');
    }

    // -----------------------------------------------------------------------
    //  Validation
    // -----------------------------------------------------------------------

    /**
     * Validates and normalizes the incoming payload for create/update.
     * Stops with a 422/400 response when invalid; otherwise returns a
     * clean array ready for the repository.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function validatePayload(array $data): array
    {
        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->required('category_id')
            ->positiveInteger('category_id')
            ->maxLength('address', 255)
            ->intBetween('price_level', 1, 5)
            ->inList('status', self::STATUSES);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $categoryId = (int) $data['category_id'];

        // Enforce the NOT NULL foreign key: the category must exist.
        if ($this->categoryRepository->findById($categoryId) === null) {
            Response::unprocessable('Validation failed.', [
                'category_id' => "Category with id {$categoryId} does not exist.",
            ]);
        }

        // Build the normalized record. Status falls back to the DB default.
        return [
            'name'            => trim((string) $data['name']),
            'category_id'     => $categoryId,
            'description'     => $this->optionalString($data, 'description'),
            'address'         => $this->optionalString($data, 'address'),
            'google_maps_url' => $this->optionalString($data, 'google_maps_url'),
            'price_level'     => $this->optionalInt($data, 'price_level'),
            'status'          => isset($data['status']) && $data['status'] !== ''
                ? (string) $data['status']
                : 'Open',
        ];
    }

}
