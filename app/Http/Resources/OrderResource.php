<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->order_number ?? $this->order_id,
            'status' => $this->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->status)),
            'total' => (int) ($this->total ?? $this->amount),
            'port_name' => $this->port_name,
            'buyer' => new UserProfileResource($this->whenLoaded('buyer')),
            'exporter' => new UserProfileResource($this->whenLoaded('exporter')),
            'batch' => new BatchResource($this->whenLoaded('batchListing') ?? $this->whenLoaded('batch')),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
