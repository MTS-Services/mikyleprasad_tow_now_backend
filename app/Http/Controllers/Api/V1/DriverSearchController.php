<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FindDriversRequest;
use App\Http\Resources\Api\V1\DriverCardResource;
use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Services\DriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverSearchController extends Controller
{
    public function __construct(
        private readonly DriverService $driverService
    ) {}

    public function index(FindDriversRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $lowBandwidth = filter_var((string) $request->header('X-Low-Bandwidth', '0'), FILTER_VALIDATE_BOOLEAN);
        if ($lowBandwidth) {
            $filters['lite'] = true;
            // Keep payloads smaller for unstable mobile links.
            $filters['per_page'] = min((int) ($filters['per_page'] ?? 8), 8);
        }

        ksort($filters);
        $cacheKey = 'driver_search:index:'.sha1(json_encode([
            'filters' => $filters,
            'lang' => $request->header('Accept-Language'),
            'low' => $lowBandwidth,
        ], JSON_THROW_ON_ERROR));

        $payload = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($filters): array {
            $paginator = $this->driverService->paginate($filters);
            $resourcePayload = DriverCardResource::collection($paginator)->response()->getData(true);

            return [
                'success' => true,
                'message' => 'Drivers fetched successfully.',
                'data' => $resourcePayload['data'] ?? [],
                'links' => $resourcePayload['links'] ?? null,
                'meta' => $resourcePayload['meta'] ?? null,
            ];
        });

        return response()->json($payload, HttpStatus::HTTP_OK);
    }

    public function show(int $id): JsonResponse
    {
        $cacheKey = Str::of("driver_search:show:{$id}")->toString();
        $payload = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($id): ?array {
            $driver = User::query()
                ->whereKey($id)
                ->where('role', 'driver')
                ->where('approval_status', ApprovalStatus::APPROVED->value)
                ->where('is_suspended', false)
                ->with('vehicle')
                ->first();

            if (! $driver) {
                return null;
            }

            return [
                'success' => true,
                'message' => 'Driver fetched successfully.',
                'data' => (new DriverCardResource($driver))->resolve(),
            ];
        });

        if ($payload === null) {
            return sendResponse(false, 'Driver not found.', statusCode: HttpStatus::HTTP_NOT_FOUND);
        }

        return response()->json($payload, HttpStatus::HTTP_OK);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = Cache::remember('driver_search_stats_v1', now()->addSeconds(30), static function (): array {
            $base = User::query()
                ->where('role', 'driver')
                ->where('approval_status', ApprovalStatus::APPROVED->value)
                ->where('is_suspended', false);

            return [
                'total' => (clone $base)->count(),
                'online' => (clone $base)->where('status', 'active')->count(),
                'featured' => (clone $base)->where('is_featured', true)->count(),
            ];
        });

        return sendResponse(
            status: true,
            message: 'Driver stats fetched successfully.',
            data: $stats,
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
