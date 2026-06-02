<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * PlaceContactController.php
 * HTTP/validation layer for the `place_contacts` resource.
 *
 * Endpoints:
 *   GET    /api/v1/place-contacts          -> index (optional ?place_id)
 *   GET    /api/v1/place-contacts/{id}     -> show
 *   POST   /api/v1/place-contacts          -> store
 *   PUT    /api/v1/place-contacts/{id}     -> update
 *   DELETE /api/v1/place-contacts/{id}     -> destroy
 */

class PlaceContactController extends BaseController
{
    private PlaceContactRepository $repository;
    private PlaceRepository $placeRepository;

    /** Allowed values for the `platform` ENUM column. */
    private const PLATFORMS = ['phone', 'whatsapp', 'instagram', 'website'];

    public function __construct()
    {
        $this->repository      = new PlaceContactRepository();
        $this->placeRepository = new PlaceRepository();
    }

    public function index(): void
    {
        $params  = $this->paginationParams();
        $placeId = isset($_GET['place_id']) && $_GET['place_id'] !== ''
            ? max(1, (int) $_GET['place_id'])
            : null;

        $contacts = $this->repository->getAll($params['limit'], $params['offset'], $placeId);
        $total    = $this->repository->countAll($placeId);

        Response::ok('Place contacts retrieved successfully.', [
            'items'      => $contacts,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    public function show(string $id): void
    {
        $contactId = $this->parseId($id);

        $contact = $this->repository->findById($contactId);

        if ($contact === null) {
            Response::notFound("Place contact with id {$contactId} was not found.");
        }

        Response::ok('Place contact retrieved successfully.', $contact);
    }

    public function store(): void
    {
        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $newId   = $this->repository->create($validated);
        $contact = $this->repository->findById($newId);

        Response::created('Place contact created successfully.', $contact);
    }

    public function update(string $id): void
    {
        $contactId = $this->parseId($id);

        if ($this->repository->findById($contactId) === null) {
            Response::notFound("Place contact with id {$contactId} was not found.");
        }

        $data      = $this->getJsonBody();
        $validated = $this->validatePayload($data);

        $this->repository->update($contactId, $validated);
        $contact = $this->repository->findById($contactId);

        Response::ok('Place contact updated successfully.', $contact);
    }

    public function destroy(string $id): void
    {
        $contactId = $this->parseId($id);

        if ($this->repository->findById($contactId) === null) {
            Response::notFound("Place contact with id {$contactId} was not found.");
        }

        $this->repository->delete($contactId);

        Response::ok('Place contact deleted successfully.');
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
            ->required('platform')
            ->inList('platform', self::PLATFORMS)
            ->required('contact_value')
            ->maxLength('contact_value', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $placeId = (int) $data['place_id'];

        if ($this->placeRepository->findById($placeId) === null) {
            Response::unprocessable('Validation failed.', [
                'place_id' => "Place with id {$placeId} does not exist.",
            ]);
        }

        return [
            'place_id'      => $placeId,
            'platform'      => (string) $data['platform'],
            'contact_value' => trim((string) $data['contact_value']),
        ];
    }
}
