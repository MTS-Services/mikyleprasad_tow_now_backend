<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource) && isset($this->resource['admin'])) {
            return [
                'admin' => [
                    'id' => $this->resource['admin']->id,
                    'name' => $this->resource['admin']->name,
                    'email' => $this->resource['admin']->email,
                    'phone' => $this->resource['admin']->phone,
                    'avatar' => $this->resource['admin']->avatar,
                ],
                'site_setting' => $this->resource['site_setting'] ? [
                    'id' => $this->resource['site_setting']->id,
                    'site_email' => $this->resource['site_setting']->site_email,
                    'site_phone' => $this->resource['site_setting']->site_phone,
                    'site_address' => $this->resource['site_setting']->site_address,
                ] : null
            ];
        }

        if (is_object($this->resource)) {
            return [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'phone' => $this->resource->phone,
                'avatar' => $this->resource->avatar,
                'avatar_url' => $this->avatar_url,
            ];
        }

        return [];
    }
}
