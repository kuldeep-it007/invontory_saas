<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Catalog hierarchy management: Category → Collection → Colour (R11.2).
 *
 * Focused single-responsibility service the thin
 * {@see \App\Http\Controllers\CatalogController} delegates to. The hierarchy
 * is NOT quota-counted (only products are, via {@see ProductService}); this
 * service simply manages the structural levels and exposes paginated,
 * eager-loaded listings for the catalog management UI.
 */
class CatalogService
{
    /**
     * Paginated categories (R11.2).
     *
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginateCategories(int $perPage = 25): LengthAwarePaginator
    {
        return Category::query()->orderBy('name')->paginate($perPage);
    }

    /**
     * Paginated collections with their owning category eager-loaded (R11.2).
     *
     * @return LengthAwarePaginator<int, Collection>
     */
    public function paginateCollections(int $perPage = 25): LengthAwarePaginator
    {
        return Collection::query()->with('category')->orderBy('name')->paginate($perPage);
    }

    /**
     * Paginated colours with their owning collection eager-loaded (R11.2).
     *
     * @return LengthAwarePaginator<int, Colour>
     */
    public function paginateColours(int $perPage = 25): LengthAwarePaginator
    {
        // Eager-load the full chain Colour → Collection → Category so the
        // management UI can show which category/collection each colour sits
        // under without extra queries (R11.2).
        return Colour::query()->with('collection.category')->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a Category (R11.2). A `code` is auto-derived from the name when
     * not supplied (Rosetta-style short identifier); `active` defaults true.
     *
     * @param  array{code?: string|null, name: string, active?: bool}  $attributes
     */
    public function createCategory(array $attributes): Category
    {
        $attributes['code'] = $this->resolveCode(Category::class, $attributes);

        return Category::create($attributes);
    }

    /**
     * Create a Collection under a Category (R11.2).
     *
     * @param  array{category_id: int, code?: string|null, name: string, active?: bool}  $attributes
     */
    public function createCollection(array $attributes): Collection
    {
        $attributes['code'] = $this->resolveCode(Collection::class, $attributes);

        return Collection::create($attributes);
    }

    /**
     * Create a Colour under a Collection (R11.2).
     *
     * @param  array{collection_id: int, code?: string|null, name: string, active?: bool}  $attributes
     */
    public function createColour(array $attributes): Colour
    {
        $attributes['code'] = $this->resolveCode(Colour::class, $attributes);

        return Colour::create($attributes);
    }

    /**
     * Update a catalog level (rename, change code, activate/deactivate).
     *
     * @param  Category|Collection|Colour  $model
     * @param  array<string, mixed>  $attributes
     */
    public function updateAttribute(Category|Collection|Colour $model, array $attributes): Category|Collection|Colour
    {
        $model->fill(array_filter(
            [
                'code' => $attributes['code'] ?? null,
                'name' => $attributes['name'] ?? null,
                'active' => $attributes['active'] ?? null,
            ],
            static fn ($v) => $v !== null,
        ))->save();

        return $model->refresh();
    }

    /**
     * Derive a tenant-unique `code` from a supplied value or the name. Falls
     * back to a slug of the name, de-duplicated with a numeric suffix.
     *
     * @param  class-string<Category|Collection|Colour>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCode(string $modelClass, array $attributes): string
    {
        $supplied = isset($attributes['code']) ? trim((string) $attributes['code']) : '';
        $base = $supplied !== ''
            ? strtoupper($supplied)
            : strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($attributes['name'] ?? 'ITEM')), 0, 48));
        $base = trim($base, '-') ?: 'ITEM';

        $code = $base;
        $i = 1;
        while ($modelClass::query()->where('code', $code)->exists()) {
            $code = $base.'-'.(++$i);
        }

        return $code;
    }

    /**
     * Delete a Category (R11.2). Guarded: a category that still has collections
     * cannot be deleted — the caller must remove or reassign its collections
     * first. Throws a 422 {@see HttpException} naming the blocker so the
     * centralized renderer returns the standard error envelope.
     */
    public function deleteCategory(Category $category): void
    {
        $count = $category->collections()->count();
        if ($count > 0) {
            $this->blocked("Category has {$count} collection(s). Delete or reassign them first.");
        }

        $category->delete();
    }

    /**
     * Delete a Collection (R11.2). Guarded against deleting a collection that
     * still has colours or products attached.
     */
    public function deleteCollection(Collection $collection): void
    {
        $colours = $collection->colours()->count();
        $products = $collection->products()->count();
        if ($colours > 0 || $products > 0) {
            $this->blocked("Collection has {$colours} colour(s) and {$products} product(s). Remove them first.");
        }

        $collection->delete();
    }

    /**
     * Delete a Colour (R11.2). Guarded against deleting a colour still used by
     * products.
     */
    public function deleteColour(Colour $colour): void
    {
        $products = $colour->products()->count();
        if ($products > 0) {
            $this->blocked("Colour is used by {$products} product(s). Remove them first.");
        }

        $colour->delete();
    }

    /**
     * Reject a delete that would orphan child records, as HTTP 422.
     */
    private function blocked(string $message): never
    {
        throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, $message);
    }
}
