<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminLeadsResource;
use App\Services\ContactQueryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ContactQueryController extends Controller
{
    private ContactQueryService $contactQueryService;

    public function __construct()
    {
        $this->contactQueryService = app(ContactQueryService::class);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'created_at' => now(),
        ]);

        $this->contactQueryService->create($validated);

        return sendResponse(
            status: true,
            message: 'Contact query created successfully',
            data: null,
            statusCode: 201
        );
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $contactQueries = $this->contactQueryService->paginate($filters);

        return sendResponse(true, 'Data retrieved successfully', AdminLeadsResource::collection($contactQueries), HttpStatus::HTTP_OK);
    }

    public function show($id)
    {
        $contactQuery = $this->contactQueryService->get($id);

        return sendResponse(true, 'Contact query retrieved successfully', new AdminLeadsResource($contactQuery), HttpStatus::HTTP_OK);
    }
}
