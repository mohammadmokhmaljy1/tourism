<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * WorkingHourController.php
 * HTTP/validation layer for the `working_hours` resource.
 *
 * Endpoints:
 *   GET    /api/v1/working-hours          -> index (optional ?place_id)
 *   GET    /api/v1/working-hours/{id}     -> show
 *   POST   /api/v1/working-hours          -> store
 *   PUT    /api/v1/working-hours/{id}     -> update
 *   DELETE /api/v1/working-hours/{id}     -> destroy
 */

class WorkingHourController extends BaseController
{
    private WorkingHourRepository $repository;
    private PlaceRepository $placeRepository;

    public function __construct()
    {
        $this->repository      = new WorkingHourRepository();
        $this->placeRepository = new PlaceRepository();
    }

    public function index(): void
    {
        $params  = $this->paginationParams();
        $placeId = isset($_GET['place_id']) && $_GET['place_id'] !== ''
            ? max(1, (int) $_GET['place_id'])
            : null;

        $hours = $this->repository->getAll($params['limit'], $params['offset'], $placeId);
        $total = $this->repository->countAll($placeId);

        Response::ok('Working hours retrieved successfully.', [
            'items'      => $hours,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    public function show(string $id): void
    {
        $hourId = $this->parseId($id);

        $hour = $this->repository->findById($hourId);

        if ($hour === null) {
            Response::notFound("Working hour with id {$hourId} was not found.");
        }

        Response::ok('Working hour retrieved successfully.', $hour);
    }

    public function store(): void
    {
        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $newId = $this->repository->create($validated);
        $hour  = $this->repository->findById($newId);

        Response::created('Working hour created successfully.', $hour);
    }

    public function update(string $id): void
    {
        $hourId = $this->parseId($id);

        if ($this->repository->findById($hourId) === null) {
            Response::notFound("Working hour with id {$hourId} was not found.");
        }

        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $this->repository->update($hourId, $validated);
        $hour = $this->repository->findById($hourId);

        Response::ok('Working hour updated successfully.', $hour);
    }

    public function destroy(string $id): void
    {
        $hourId = $this->parseId($id);

        if ($this->repository->findById($hourId) === null) {
            Response::notFound("Working hour with id {$hourId} was not found.");
        }

        $this->repository->delete($hourId);

        Response::ok('Working hour deleted successfully.');
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
            ->required('day_of_week')
            ->intBetween('day_of_week', 0, 6)
            ->time('open_time')
            ->time('close_time');

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $placeId = (int) $data['place_id'];

        if ($this->placeRepository->findById($placeId) === null) {
            Response::unprocessable('Validation failed.', [
                'place_id' => "Place with id {$placeId} does not exist.",
            ]);
        }

        $isClosed = $this->boolToInt($data, 'is_closed', false);

        return [
            'place_id'    => $placeId,
            // day_of_week may legitimately be 0 (Sunday), so cast directly.
            'day_of_week' => (int) $data['day_of_week'],
            // When the day is marked closed, the open/close times are cleared.
            'open_time'   => $isClosed === 1 ? null : $this->optionalString($data, 'open_time'),
            'close_time'  => $isClosed === 1 ? null : $this->optionalString($data, 'close_time'),
            'is_closed'   => $isClosed,
        ];
    }
}
