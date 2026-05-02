<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $request->user()
            ->products()
            ->with(['currency', 'translations'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return sendResponse(
            status: true,
            message: __('common.success'),
            data: ProductResource::collection($paginator),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::query()->create([
            'user_id' => $request->user()->id,
            'currency_id' => $validated['currency_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => Product::uniqueSlugFrom($validated['name']),
            'status' => $validated['status'] ?? 'draft',
            'price' => $validated['price'] ?? null,
        ]);

        $sourceLocale = $request->user()->locale ?? config('app.locale', 'en');
        $product->autoTranslate([
            'name' => $product->name,
            'description' => (string) ($product->description ?? ''),
        ], $sourceLocale);

        $product->load(['currency', 'translations']);

        return sendResponse(
            status: true,
            message: __('common.created'),
            data: new ProductResource($product),
            statusCode: HttpStatus::HTTP_CREATED
        );
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->ensureOwner($request, $product);

        $product->load(['currency', 'translations']);

        return sendResponse(
            status: true,
            message: __('common.success'),
            data: new ProductResource($product),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->ensureOwner($request, $product);

        $validated = $request->validated();

        if ($validated !== []) {
            $product->fill($validated);

            if (array_key_exists('name', $validated) && $validated['name'] !== null && $validated['name'] !== '') {
                $product->slug = Product::uniqueSlugFrom($validated['name'], $product->id);
            }

            $product->save();
        }

        $sourceLocale = $request->user()->locale ?? config('app.locale', 'en');
        $product->autoTranslate([
            'name' => $product->name,
            'description' => (string) ($product->description ?? ''),
        ], $sourceLocale);

        $product->load(['currency', 'translations']);

        return sendResponse(
            status: true,
            message: __('common.updated'),
            data: new ProductResource($product),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->ensureOwner($request, $product);

        $product->delete();

        return sendResponse(
            status: true,
            message: __('common.deleted'),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    private function ensureOwner(Request $request, Product $product): void
    {
        abort_unless(
            (int) $product->user_id === (int) $request->user()->id,
            HttpStatus::HTTP_FORBIDDEN
        );
    }
}
