<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * CategoryController.php
 * Handles HTTP request/response and validation for the
 * `categories` resource. All database work is delegated to
 * CategoryRepository (Repository Pattern).
 *
 * Endpoints:
 *   GET    /api/v1/categories          -> index   (paginate + search)
 *   GET    /api/v1/categories/{id}     -> show
 *   POST   /api/v1/categories          -> store
 *   PUT    /api/v1/categories/{id}     -> update
 *   DELETE /api/v1/categories/{id}     -> destroy
 */

class CategoryController extends BaseController
{
    private CategoryRepository $repository;

    public function __construct()
    {
        $this->repository = new CategoryRepository();
    }

    /**
     * GET /api/v1/categories
     * Returns a paginated, optionally searched collection.
     */
    public function index(): void
    {
        $params = $this->paginationParams();

        $categories = $this->repository->getAll($params['limit'], $params['offset'], $params['search']);
        $total      = $this->repository->countAll($params['search']);

        Response::ok('Categories retrieved successfully.', [
            'items'      => $categories,
            'pagination' => $this->buildPagination($total, $params['page'], $params['limit']),
        ]);
    }

    /**
     * GET /api/v1/categories/{id}
     * Returns a single category or a 404.
     */
    public function show(string $id): void
    {
        $categoryId = $this->parseId($id);

        $category = $this->repository->findById($categoryId);

        if ($category === null) {
            Response::notFound("Category with id {$categoryId} was not found.");
        }

        Response::ok('Category retrieved successfully.', $category);
    }

    /**
     * POST /api/v1/categories
     * Validates the body and creates a new category.
     */
    public function store(): void
    {
        $data = $this->getJsonBody();

        // Validate: name required (max 255); icon optional (max 255).
        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->maxLength('icon', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $name = trim((string) $data['name']);
        $icon = $this->optionalString($data, 'icon');

        $newId    = $this->repository->create($name, $icon);
        $category = $this->repository->findById($newId);

        Response::created('Category created successfully.', $category);
    }

    /**
     * PUT /api/v1/categories/{id}
     * Fully updates an existing category.
     */
    public function update(string $id): void
    {
        $categoryId = $this->parseId($id);

        // Make sure it exists before attempting the update.
        if ($this->repository->findById($categoryId) === null) {
            Response::notFound("Category with id {$categoryId} was not found.");
        }

        $data = $this->getJsonBody();

        $validator = (new Validator($data))
            ->required('name')
            ->maxLength('name', 255)
            ->maxLength('icon', 255);

        if ($validator->fails()) {
            Response::unprocessable('Validation failed.', $validator->errors());
        }

        $name = trim((string) $data['name']);
        $icon = $this->optionalString($data, 'icon');

        $this->repository->update($categoryId, $name, $icon);
        $category = $this->repository->findById($categoryId);

        Response::ok('Category updated successfully.', $category);
    }

    /**
     * DELETE /api/v1/categories/{id}
     * Deletes a category, guarding against the RESTRICT FK constraint.
     */
    public function destroy(string $id): void
    {
        $categoryId = $this->parseId($id);

        if ($this->repository->findById($categoryId) === null) {
            Response::notFound("Category with id {$categoryId} was not found.");
        }

        // The places.category_id FK is ON DELETE RESTRICT, so block the
        // delete with a clear message instead of a raw database error.
        if ($this->repository->countLinkedPlaces($categoryId) > 0) {
            Response::badRequest(
                'This category cannot be deleted because it still has places linked to it.'
            );
        }

        $this->repository->delete($categoryId);

        Response::ok('Category deleted successfully.');
    }
}
