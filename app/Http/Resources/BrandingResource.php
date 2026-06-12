<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return [
            'palette_key' => $payload['palette_key'],
            'primary_color' => $payload['primary'],
            'secondary_color' => $payload['secondary'],
            'accent_color' => $payload['accent'],
            'on_primary' => $payload['on_primary'],
            'on_secondary' => $payload['on_secondary'],
            'logo_url' => $payload['logo_url'],
            'brand_name' => $payload['brand_name'],
            'updated_at' => $payload['updated_at'],
        ];
    }
}
