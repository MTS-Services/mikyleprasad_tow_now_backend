<?php

namespace App\Filters\Api\V1\Manufacturer;

use Illuminate\Database\Eloquent\Builder;

class ProductFilter
{
   private function __construct(private Builder $query) {}


   public static function apply(Builder $query, $request): Builder
   {
     return (new self($query))
        ->withSearch($request->searchTerm())
        ->withFilterStatus($request->status())
        ->onlyMine()
        ->query;
   }

   public function withSearch(?string $term): self
   {
       if ($term === null) {
           return $this;
       }

       $this->query->where('name', 'like', "%{$term}%");

       return $this;
   }

   public function onlyMine(): self
   {
       $this->query->where('user_id', request()->user()->id);

       return $this;
   }

   public function withFilterStatus(?string $status): self
   {
       if ($status === null) {
           return $this;
       }

       $this->query->where('status', $status);

       return $this;
   }
}
