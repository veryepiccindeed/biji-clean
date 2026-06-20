<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->batch_id ?? $this->id,
            'batch_id' => $this->batch_id,
            'code' => $this->batch_code,
            'name' => $this->name ?? 'Batch ' . $this->batch_code,
            'variety' => $this->variety ?? $this->varietas,
            'quantity' => (int) ($this->quantity ?? $this->jumlah_karung),
            'status' => $this->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->status)),
            'health_status' => $this->health_status,
            'price' => (int) $this->price,
            'elevation_mdpl' => $this->elevation_mdpl,
            'blockchain_status' => $this->blockchain_status,
            'certificate_id' => $this->certificate_id,
            'farmer' => new UserProfileResource($this->whenLoaded('farmer')),
            'exporter' => new UserProfileResource($this->whenLoaded('exporter')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
