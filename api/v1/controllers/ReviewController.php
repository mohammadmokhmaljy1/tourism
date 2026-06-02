<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * ReviewController.php
 * HTTP/validation layer for the `reviews` resource.
 *
 * Endpoints:
 *   GET    /api/v1/reviews          -> index (paginate + search, ?user_id / ?place_id)
 *   GET    /api/v1/reviews/{id}     -> show
 *   POST   /api/v1/reviews          -> store
 *   PUT    /api/v1/reviews/{id}     -> update
 *   DELETE /api/v1/reviews/{id}     -> destroy
 */

class ReviewController extends BaseController
{
    private ReviewRepository $repository;
    private UserRepository $userRepository;
    private PlaceRepository $placeRepository;

    public function __construct()
    {
        $this->repository      = new ReviewRepository();
        $this->userRepository  = new UserRepository();
        $this->placeRepository = new PlaceRepository();
    }

    public function index(): void
    {
        $params  = $this->paginationParams();
        $userId  = isset($_GET['user_id'])  && $_GET['user_id']  !== '' ? max(1, (int) $_GET['user_id'])  : null;
        $placeId = isset($_GET['place_id']) && $_GET['place_id'] !== '' ? max(1, (int) $_GET['place_id']) : null;

        $reviews = $this->repository->getAll($params['limit'], $params['offset'], $params['search'], $userId, $placeId);
        $total   = $this->repository->countAll($params['search'], $userId, $placeId);

        Response::ok('Reviews retrieved successfully.', [
            'items'      => $reviews,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    public function show(string $id): void
    {
        $reviewId = $this->parseId($id);

        $review = $this->repository->findById($reviewId);

        if ($review === null) {
            Response::notFound("Review with id {$reviewId} was not found.");
        }

        Response::ok('Review retrieved successfully.', $review);
    }

    public function store(): void
    {
        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $newId  = $this->repository->create($validated);
        $review = $this->repository->findById($newId);

        Response::created('Review created successfully.', $review);
    }

    public function update(string $id): void
    {
        $reviewId = $this->parseId($id);

        if ($this->repository->findById($reviewId) === null) {
            Response::notFound("Review with id {$reviewId} was not found.");
        }

        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $this->repository->update($reviewId, $validated);
        $review = $this->repository->findById($reviewId);

        Response::ok('Review updated successfully.', $review);
    }

    public function destroy(string $id): void
    {
        $reviewId = $this->parseId($id);

        if ($this->repository->findById($reviewId) === null) {
            Response::notFound("Review with id {$reviewId} was not found.");
        }

        $this->repository->delete($reviewId);

        Response::ok('Review deleted successfully.');
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function validatePayload(array $data): array
    {
        $validator = (new Validator($data))
            ->required('user_id')
            ->positiveInteger('user_id')
            ->required('place_id')
            ->positiveInteger('place_id')
            ->required('rating')
            ->intBetween('rating', 1, 5);

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

        return [
            'user_id'  => $userId,
            'place_id' => $placeId,
            'rating'   => (int) $data['rating'],
            'comment'  => $this->optionalString($data, 'comment'),
        ];
    }
}
