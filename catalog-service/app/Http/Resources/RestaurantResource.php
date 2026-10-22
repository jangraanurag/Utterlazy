<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'is_active' => $this->is_active,
            'is_accepting_orders' => $this->is_accepting_orders,
            'auto_accept_orders' => $this->auto_accept_orders,
            'manually_offline' => $this->manually_offline,
            'paused_until' => $this->paused_until ? $this->paused_until->toIso8601String() : null,
            'permanent_offline' => $this->permanent_offline,
            'operating_hours' => $this->operating_hours,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'gst_number' => $this->gst_number,
            'is_gst_ready' => ! empty($this->gst_number),
            'distance_km' => $this->distance_km !== null ? round($this->distance_km, 1) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
