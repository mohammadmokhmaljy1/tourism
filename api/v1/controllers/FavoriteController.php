<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * FavoriteController.php
 * HTTP/validation layer for the `favorites` resource.
 *
 * Endpoints:
 *   GET    /api/v1/favorites          -> index (optional ?user_id / ?place_id)
 *   GET    /api/v1/favorites/{id}     -> show
 *   POST   /api/v1/favorites          -> store
 *   PUT    /api/v1/favorites/{id}     -> update
 *   DELETE /api/v1/favorites/{id}     -> destroy
 */

class FavoriteController extends BaseController
{
    private FavoriteRepository $repository;
    private UserRepository $userRepository;
    private PlaceRepository $placeRepository;

    public function __construct()
    {
        $this->repository      = new FavoriteRepository();
        $this->userRepository  = new UserRepository();
        $this->placeRepository = new PlaceRepository();
    }

    public function index(): void
    {
        $params  = $this->paginationParams();
        $userId  = isset($_GET['user_id'])  && $_GET['user_id']  !== '' ? max(1, (int) $_GET['user_id'])  : null;
        $placeId = isset($_GET['place_id']) && $_GET['place_id'] !== '' ? max(1, (int) $_GET['place_id']) : null;

        $favorites = $this->repository->getAll($params['limit'], $params['offset'], $userId, $placeId);
        $total     = $this->repository->countAll($userId, $placeId);

        Response::ok('Favorites retrieved successfully.', [
            'items'      => $favorites,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    public function show(string $id): void
    {
        $favoriteId = $this->parseId($id);

        $favorite = $this->repository->findById($favoriteId);

        if ($favorite === null) {
            Response::notFound("Favorite with id {$favoriteId} was not found.");
        }

        Response::ok('Favorite retrieved successfully.', $favorite);
    }

    public function store(): void
    {
        $data = $this->getJsonBody();
        [$userId, $placeId] = $this->validatePayload($data);

        // Respect the UNIQUE (user_id, place_id) constraint.
        if ($this->repository->pairExists($userId, $placeId)) {
            Response::unprocessable('Validation failed.', [
                'place_id' => 'This place is already in the user\'s favorites.',
            ]);
        }

        $newId    = $this->repository->create($userId, $placeId);
        $favorite = $this->repository->findById($newId);

        Response::created('Favorite created successfully.', $favorite);
    }

    public function update(string $id): void
    {
        $favoriteId = $this->parseId($id);

        if ($this->repository->findById($favoriteId) === null) {
            Response::notFound("Favorite with id {$favoriteId} was not found.");
        }

        $data = $this->getJsonBody();
        [$userId, $placeId] = $this->validatePayload($data);

        if ($this->repository->pairExists($userId, $placeId, $favoriteId)) {
            Response::unprocessable('Validation failed.', [
                'place_id' => 'This place is already in the user\'s favorites.',
            ]);
        }

        $this->repository->update($favoriteId, $userId, $placeId);
        $favorite = $this->repository->findById($favoriteId);

        Response::ok('Favorite updated successfully.', $favorite);
    }

    public function destroy(string $id): void
    {
        $favoriteId = $this->parseId($id);

        if ($this->repository->findById($favoriteId) === null) {
            Response::notFound("Favorite with id {$favoriteId} was not found.");
        }

        $this->repository->delete($favoriteId);

        Response::ok('Favorite deleted successfully.');
    }

    /**
     * Validates user_id + place_id and confirms both records exist.
     *
     * @param array<string,mixed> $data
     * @return array{0:int,1:int} [userId, placeId]
     */
    private function validatePayload(array $data): array
    {
        $validator = (new Validator($data))
            ->required('user_id')
            ->positiveInteger('user_id')
            ->required('place_id')
            ->positiveInteger('place_id');

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $userId  = (int) $data['user_id'];
        $placeId = (int) $data['place_id'];

        $errors = [];
        if ($this->userRepository->findById($userId) === null) {
            $errors['user_id'] = "User with id {$userId} does not exist.";
        }
        if ($this->placeRepository->findById($placeId) === null) {
            $errors['place_id'] = "Place with id {$placeId} does not exist.";
        }

        if ($errors) {
            Response::unprocessable('Validation failed.', $errors);
        }

        return [$userId, $placeId];
    }
}
