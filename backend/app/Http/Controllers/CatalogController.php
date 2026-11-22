<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CatalogVisibilityRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\StoreColourRequest;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Services\CatalogService;
use App\Services\CatalogVisibilityService;
use App\Support\CatalogOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalog hierarchy endpoints: Category → Collection → Colour (R11.2) and the
 * Buyer-scoped catalog browse (R11.4, R11.5, R11.6). Thin controller delegating
 * to {@see CatalogService} and {@see CatalogVisibilityService}. Runs on the
 * tenant pipeline, so every action is confined to the active tenant database.
 * List endpoints are paginated and eager-loaded by the services.
 *
 * Ownership: the Company and each Distributor manage their own hierarchy.
 * Lists return Company-owned rows by default (the orderable catalogue) and the
 * caller's own rows with `?owner=mine` (management screens). Updates and
 * deletes of another owner's rows answer 404.
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CatalogVisibilityService $visibility,
    ) {}

    /**
     * GET /api/catalog — the paginated catalog a Buyer may browse (R11.4,
     * R11.5, R11.6).
     *
     * The optional `buyer_type`/`buyer_id` selector scopes the catalog to a
     * Distributor or Dealer: when that Buyer has active product attributes
     * configured, only the permitted Categories/Collections/Colours are shown
     * (R11.4, R11.5); when the Buyer is unconfigured — or no Buyer selector is
     * supplied — the full tenant catalog is returned (R11.6). The service
     * paginates and eager-loads against the active tenant database.
     */
    public function catalog(CatalogVisibilityRequest $request): JsonResponse
    {
        $buyerClass = $request->buyerClass();

        if ($buyerClass === null) {
            // No Buyer selected: unrestricted catalog (R11.6).
            return response()->json($this->visibility->paginateForAll());
        }

        /** @var Model $buyer */
        $buyer = $buyerClass::query()->findOrFail($request->validated()['buyer_id']);

        return response()->json($this->visibility->paginateForBuyer($buyer));
    }

    public function categories(Request $request): JsonResponse
    {
        return response()->json($this->catalog->paginateCategories($this->listOwnerKey($request)));
    }

    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->catalog->createCategory($request->validated(), CatalogOwner::distributorColumnFor($request->user()))],
            201,
        );
    }

    public function collections(Request $request): JsonResponse
    {
        return response()->json($this->catalog->paginateCollections($this->listOwnerKey($request)));
    }

    public function storeCollection(StoreCollectionRequest $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->catalog->createCollection($request->validated(), CatalogOwner::distributorColumnFor($request->user()))],
            201,
        );
    }

    public function colours(Request $request): JsonResponse
    {
        return response()->json($this->catalog->paginateColours($this->listOwnerKey($request)));
    }

    public function storeColour(StoreColourRequest $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->catalog->createColour($request->validated(), CatalogOwner::distributorColumnFor($request->user()))],
            201,
        );
    }

    /** PATCH /api/categories/{category} — rename / recode / activate (R11.2). */
    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $category);
        $data = $this->validateUpdate($request, 'categories', $category->getKey());

        return response()->json(['data' => $this->catalog->updateAttribute($category, $data)]);
    }

    /** PATCH /api/collections/{collection} — rename / recode / activate (R11.2). */
    public function updateCollection(Request $request, Collection $collection): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $collection);
        $data = $this->validateUpdate($request, 'collections', $collection->getKey());

        return response()->json(['data' => $this->catalog->updateAttribute($collection, $data)]);
    }

    /** PATCH /api/colours/{colour} — rename / recode / activate (R11.2). */
    public function updateColour(Request $request, Colour $colour): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $colour);
        $data = $this->validateUpdate($request, 'colours', $colour->getKey());

        return response()->json(['data' => $this->catalog->updateAttribute($colour, $data)]);
    }

    /**
     * Shared validation for a catalog-level update. `code` must stay unique
     * among the owner's rows (ignoring the row being edited).
     *
     * @return array<string, mixed>
     */
    private function validateUpdate(Request $request, string $table, int $id): array
    {
        return $request->validate([
            'code' => ['sometimes', 'nullable', 'string', 'max:64', CatalogOwner::ownedUniqueRule($request->user(), $table, 'code', $id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * DELETE /api/categories/{category} — delete a category (R11.2). Blocked
     * with 422 if it still has collections.
     */
    public function destroyCategory(Request $request, Category $category): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $category);
        $this->catalog->deleteCategory($category);

        return response()->json(['message' => 'Category deleted.']);
    }

    /**
     * DELETE /api/collections/{collection} — delete a collection (R11.2).
     * Blocked with 422 if it still has colours or products.
     */
    public function destroyCollection(Request $request, Collection $collection): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $collection);
        $this->catalog->deleteCollection($collection);

        return response()->json(['message' => 'Collection deleted.']);
    }

    /**
     * DELETE /api/colours/{colour} — delete a colour (R11.2). Blocked with 422
     * if it is still used by products.
     */
    public function destroyColour(Request $request, Colour $colour): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $colour);
        $this->catalog->deleteColour($colour);

        return response()->json(['message' => 'Colour deleted.']);
    }

    private function listOwnerKey(Request $request): int
    {
        return CatalogOwner::listKeyFor($request->user(), $request->query('owner'));
    }
}
