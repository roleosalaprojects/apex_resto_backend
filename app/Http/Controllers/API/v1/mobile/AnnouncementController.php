<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShopAnnouncementResource;
use App\Http\Traits\ApiResponse;
use App\Models\Ecommerce\ShopAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    use ApiResponse;

    private string $mediaLocation = 'shop_announcements';

    public function index(Request $request): JsonResponse
    {
        // Management mode: returns all announcements with pagination & search
        if ($request->get('scope') === 'manage') {
            $query = ShopAnnouncement::ordered();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('position')) {
                $query->where('position', $request->position);
            }

            $perPage = $request->get('per_page', 15);
            $announcements = $query->paginate($perPage);

            return $this->success([
                'announcements' => ShopAnnouncementResource::collection($announcements),
                'pagination' => [
                    'current_page' => $announcements->currentPage(),
                    'last_page' => $announcements->lastPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                ],
            ]);
        }

        // Default mode: active + scheduled only (existing behavior)
        $query = ShopAnnouncement::active()->scheduled()->ordered();

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        $limit = $request->get('limit', 10);
        $announcements = $query->limit($limit)->get();

        return $this->success([
            'announcements' => ShopAnnouncementResource::collection($announcements),
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $announcement = ShopAnnouncement::find($id);

        if (! $announcement) {
            return $this->error('Announcement not found', 404);
        }

        // In manage mode, allow viewing inactive announcements
        if ($request->get('scope') !== 'manage' && ! $announcement->isCurrentlyActive()) {
            return $this->error('Announcement not found', 404);
        }

        return $this->success([
            'announcement' => new ShopAnnouncementResource($announcement),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media' => 'required|file|mimes:jpeg,png,jpg,gif,mp4,webm,mov|max:102400',
            'media_type' => 'required|in:image,video',
            'link_url' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
            'position' => 'required|in:hero,banner,popup',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $helper = new HelperController;
        $mediaPath = $helper->uploadMedia($request, $this->mediaLocation);

        $announcement = ShopAnnouncement::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'media_path' => $mediaPath,
            'media_type' => $validated['media_type'],
            'link_url' => $validated['link_url'] ?? null,
            'link_text' => $validated['link_text'] ?? null,
            'position' => $validated['position'],
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return $this->created([
            'announcement' => new ShopAnnouncementResource($announcement),
        ], 'Announcement created successfully.');
    }

    public function update(Request $request, ShopAnnouncement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,webm,mov|max:102400',
            'media_type' => 'sometimes|in:image,video',
            'link_url' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
            'position' => 'sometimes|in:hero,banner,popup',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $helper = new HelperController;

        // Inject old_media for HelperController cleanup
        $request->merge(['old_media' => $announcement->media_path]);

        $mediaPath = $request->hasFile('media')
            ? $helper->uploadMedia($request, $this->mediaLocation)
            : $announcement->media_path;

        $updateData = array_filter([
            'title' => $validated['title'] ?? null,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
            'media_path' => $mediaPath,
            'media_type' => $validated['media_type'] ?? null,
            'link_url' => array_key_exists('link_url', $validated) ? $validated['link_url'] : null,
            'link_text' => array_key_exists('link_text', $validated) ? $validated['link_text'] : null,
            'position' => $validated['position'] ?? null,
        ], fn ($value) => $value !== null);

        // Handle fields that can be explicitly set (including to null/false/0)
        if (array_key_exists('display_order', $validated)) {
            $updateData['display_order'] = $validated['display_order'] ?? 0;
        }
        if (array_key_exists('is_active', $validated)) {
            $updateData['is_active'] = $validated['is_active'];
        }
        if (array_key_exists('starts_at', $validated)) {
            $updateData['starts_at'] = $validated['starts_at'];
        }
        if (array_key_exists('ends_at', $validated)) {
            $updateData['ends_at'] = $validated['ends_at'];
        }

        $announcement->update($updateData);

        return $this->success([
            'announcement' => new ShopAnnouncementResource($announcement),
        ], 'Announcement updated successfully.');
    }

    public function destroy(ShopAnnouncement $announcement): JsonResponse
    {
        $announcement->delete(); // Soft delete (model uses SoftDeletes)

        return $this->success(null, 'Announcement deleted successfully.');
    }
}
