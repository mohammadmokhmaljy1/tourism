<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceImageController.php
 * HTTP/validation layer for the `place_images` resource.
 *
 * Endpoints:
 *   GET    /api/v1/place-images               -> index (optional ?place_id)
 *   GET    /api/v1/place-images/{id}          -> show
 *   POST   /api/v1/place-images               -> store
 *   PUT    /api/v1/place-images/{id}          -> update
 *   DELETE /api/v1/place-images/{id}          -> destroy
 */

class PlaceImageController extends BaseController
{
    private PlaceImageRepository $repository;
    private PlaceRepository $placeRepository;

    public function __construct()
    {
        $this->repository      = new PlaceImageRepository();
        $this->placeRepository = new PlaceRepository();
    }

    public function index(): void
    {
        $params  = $this->paginationParams();
        // Optional filter: only images belonging to one place.
        $placeId = isset($_GET['place_id']) && $_GET['place_id'] !== ''
            ? max(1, (int) $_GET['place_id'])
            : null;

        $images = $this->repository->getAll($params['limit'], $params['offset'], $placeId);
        $total  = $this->repository->countAll($placeId);

        Response::ok('Place images retrieved successfully.', [
            'items'      => $images,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    public function show(string $id): void
    {
        $imageId = $this->parseId($id);

        $image = $this->repository->findById($imageId);

        if ($image === null) {
            Response::notFound("Place image with id {$imageId} was not found.");
        }

        Response::ok('Place image retrieved successfully.', $image);
    }

    public function store(): void
    {
        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $newId = $this->repository->create($validated);
        $image = $this->repository->findById($newId);

        Response::created('Place image created successfully.', $image);
    }

    public function update(string $id): void
    {
        $imageId = $this->parseId($id);

        if ($this->repository->findById($imageId) === null) {
            Response::notFound("Place image with id {$imageId} was not found.");
        }

        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $this->repository->update($imageId, $validated);
        $image = $this->repository->findById($imageId);

        Response::ok('Place image updated successfully.', $image);
    }

    public function destroy(string $id): void
    {
        $imageId = $this->parseId($id);

        if ($this->repository->findById($imageId) === null) {
            Response::notFound("Place image with id {$imageId} was not found.");
        }

        $this->repository->delete($imageId);

        Response::ok('Place image deleted successfully.');
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function validatePayload(array $data): array
    {
        $validator = (new Validator($data))
            ->required('place_id')
            ->positiveInteger('place_id')
            ->required('image_url')
            ->maxLength('image_url', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $placeId = (int) $data['place_id'];

        // Enforce the NOT NULL foreign key: the place must exist.
        if ($this->placeRepository->findById($placeId) === null) {
            Response::unprocessable('Validation failed.', [
                'place_id' => "Place with id {$placeId} does not exist.",
            ]);
        }

        return [
            'place_id'   => $placeId,
            'image_url'  => trim((string) $data['image_url']),
            'is_primary' => $this->boolToInt($data, 'is_primary', false),
        ];
    }
}
