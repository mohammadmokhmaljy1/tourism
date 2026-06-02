<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * AmenityController.php
 * HTTP/validation layer for the `amenities` resource.
 *
 * Endpoints:
 *   GET    /api/v1/amenities          -> index   (paginate + search)
 *   GET    /api/v1/amenities/{id}     -> show
 *   POST   /api/v1/amenities          -> store
 *   PUT    /api/v1/amenities/{id}     -> update
 *   DELETE /api/v1/amenities/{id}     -> destroy
 */

class AmenityController extends BaseController
{
    private AmenityRepository $repository;

    public function __construct()
    {
        $this->repository = new AmenityRepository();
    }

    public function index(): void
    {
        $params = $this->paginationParams();

        $amenities = $this->repository->getAll($params['limit'], $params['offset'], $params['search']);
        $total     = $this->repository->countAll($params['search']);

        Response::ok('Amenities retrieved successfully.', [
            'items'      => $amenities,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    public function show(string $id): void
    {
        $amenityId = $this->parseId($id);

        $amenity = $this->repository->findById($amenityId);

        if ($amenity === null) {
            Response::notFound("Amenity with id {$amenityId} was not found.");
        }

        Response::ok('Amenity retrieved successfully.', $amenity);
    }

    public function store(): void
    {
        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->maxLength('icon', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $newId   = $this->repository->create(trim((string) $data['name']), $this->optionalString($data, 'icon'));
        $amenity = $this->repository->findById($newId);

        Response::created('Amenity created successfully.', $amenity);
    }

    public function update(string $id): void
    {
        $amenityId = $this->parseId($id);

        if ($this->repository->findById($amenityId) === null) {
            Response::notFound("Amenity with id {$amenityId} was not found.");
        }

        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->maxLength('icon', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $this->repository->update($amenityId, trim((string) $data['name']), $this->optionalString($data, 'icon'));
        $amenity = $this->repository->findById($amenityId);

        Response::ok('Amenity updated successfully.', $amenity);
    }

    public function destroy(string $id): void
    {
        $amenityId = $this->parseId($id);

        if ($this->repository->findById($amenityId) === null) {
            Response::notFound("Amenity with id {$amenityId} was not found.");
        }

        $this->repository->delete($amenityId);

        Response::ok('Amenity deleted successfully.');
    }
}
