<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandingResource;
use App\Services\BrandingService;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingController extends Controller
{
    public function __construct(private BrandingService $branding) {}

    public function show(): JsonResource
    {
        return new BrandingResource(
            $this->branding->forCurrentTenant(),
        );
    }
}
