<?php

namespace App\Http\Controllers\API\v1\second_screen;

use App\Http\Controllers\Controller;
use App\Models\Settings\Advertisement;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function index(Request $request)
    {
        $query = Advertisement::query()
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc');

        // Optional filter by media type
        if ($request->has('media_type')) {
            $query->where('media_type', $request->input('media_type'));
        }

        $advertisements = $query->get()->map(function ($ad) {
            $mediaUrl = null;
            if ($ad->image) {
                if ($ad->isVideo()) {
                    // Use streaming endpoint for proper byte-range support
                    $filename = basename($ad->image);
                    $mediaUrl = url("/api/v1/media/stream/{$filename}");
                } else {
                    $mediaUrl = url($ad->image);
                }
            }

            return [
                'id' => $ad->id,
                'name' => $ad->name,
                'description' => $ad->description,
                'media_url' => $mediaUrl,
                'media_type' => $ad->media_type,
                'duration' => $ad->duration,
                'display_order' => $ad->display_order,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $advertisements,
            'count' => $advertisements->count(),
        ], 200);
    }
}
