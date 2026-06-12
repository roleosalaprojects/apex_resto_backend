<?php

namespace App\Http\Controllers\Admin\Ecommerce;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Models\Ecommerce\ShopAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopAnnouncementController extends Controller
{
    private string $mediaLocation = 'shop_announcements';

    public function index(): View
    {
        return view('admin.ecommerce.shop-announcements.index');
    }

    public function create(): View
    {
        $announcement = new ShopAnnouncement;
        $nextOrder = ShopAnnouncement::max('display_order') + 1;

        return view('admin.ecommerce.shop-announcements.create', compact('announcement', 'nextOrder'));
    }

    public function store(Request $request): RedirectResponse
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
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $helper = new HelperController;
        $mediaPath = $helper->uploadMedia($request, $this->mediaLocation);

        ShopAnnouncement::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'media_path' => $mediaPath,
            'media_type' => $validated['media_type'],
            'link_url' => $validated['link_url'] ?? null,
            'link_text' => $validated['link_text'] ?? null,
            'position' => $validated['position'],
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return redirect()
            ->route('shop-announcements.index')
            ->with('success', 'Shop announcement created successfully!');
    }

    public function show(ShopAnnouncement $shopAnnouncement): View
    {
        return view('admin.ecommerce.shop-announcements.show', ['announcement' => $shopAnnouncement]);
    }

    public function edit(ShopAnnouncement $shopAnnouncement): View
    {
        return view('admin.ecommerce.shop-announcements.edit', ['announcement' => $shopAnnouncement]);
    }

    public function update(Request $request, ShopAnnouncement $shopAnnouncement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,webm,mov|max:102400',
            'media_type' => 'required|in:image,video',
            'old_media' => 'nullable|string',
            'link_url' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
            'position' => 'required|in:hero,banner,popup',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $helper = new HelperController;

        $mediaPath = $request->hasFile('media')
            ? $helper->uploadMedia($request, $this->mediaLocation)
            : $validated['old_media'];

        $shopAnnouncement->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'media_path' => $mediaPath,
            'media_type' => $validated['media_type'],
            'link_url' => $validated['link_url'] ?? null,
            'link_text' => $validated['link_text'] ?? null,
            'position' => $validated['position'],
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return redirect()
            ->route('shop-announcements.index')
            ->with('info', 'Shop announcement updated successfully!');
    }

    public function destroy(ShopAnnouncement $shopAnnouncement): JsonResponse
    {
        $shopAnnouncement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shop announcement deleted successfully!',
        ]);
    }

    public function table(): JsonResponse
    {
        $query = ShopAnnouncement::query()->ordered();

        return datatables($query)
            ->addColumn('preview', function (ShopAnnouncement $announcement) {
                if ($announcement->isVideo()) {
                    return '<div class="symbol symbol-50px">
                        <div class="symbol-label bg-light-primary">
                            <i class="fas fa-video text-primary fs-3"></i>
                        </div>
                    </div>';
                }

                $imageUrl = $announcement->media_path ?: '/assets/media/svg/general/rhone.svg';

                return '<div class="symbol symbol-50px">
                    <div class="symbol-label" style="background-image:url(\'/'.$imageUrl.'\')"></div>
                </div>';
            })
            ->addColumn('type_badge', function (ShopAnnouncement $announcement) {
                return $announcement->isVideo()
                    ? '<span class="badge badge-light-primary">Video</span>'
                    : '<span class="badge badge-light-success">Image</span>';
            })
            ->addColumn('position_badge', function (ShopAnnouncement $announcement) {
                $badges = [
                    'hero' => '<span class="badge badge-light-info">Hero</span>',
                    'banner' => '<span class="badge badge-light-warning">Banner</span>',
                    'popup' => '<span class="badge badge-light-danger">Popup</span>',
                ];

                return $badges[$announcement->position] ?? '';
            })
            ->addColumn('schedule', function (ShopAnnouncement $announcement) {
                if (! $announcement->starts_at && ! $announcement->ends_at) {
                    return '<span class="text-muted">Always</span>';
                }

                $start = $announcement->starts_at?->format('M d, Y') ?? 'Now';
                $end = $announcement->ends_at?->format('M d, Y') ?? 'Forever';

                return "<small>{$start} - {$end}</small>";
            })
            ->addColumn('status_badge', function (ShopAnnouncement $announcement) {
                if ($announcement->isCurrentlyActive()) {
                    return '<span class="badge badge-light-success">Active</span>';
                }

                if (! $announcement->is_active) {
                    return '<span class="badge badge-light-danger">Inactive</span>';
                }

                return '<span class="badge badge-light-warning">Scheduled</span>';
            })
            ->addColumn('actions', function (ShopAnnouncement $announcement) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                $action .= '<a href="'.route('shop-announcements.show', $announcement->id).'"
                        class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1"
                        data-bs-toggle="tooltip" title="View">
                        <i class="fas fa-eye"></i>
                    </a>';
                $action .= '<a href="'.route('shop-announcements.edit', $announcement->id).'"
                    class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1"
                    data-bs-toggle="tooltip" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>';
                $action .= '<button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm"
                    value="'.$announcement->id.'" data-bs-toggle="tooltip" title="Delete">
                    <i class="fas fa-trash"></i></button>';
                $action .= '<input type="hidden" id="name_'.$announcement->id.'" value="'.$announcement->title.'" />';
                $action .= '<form method="POST" action="'.route('shop-announcements.destroy', $announcement->id).'" id="form_delete_'.$announcement->id.'">'.method_field('DELETE').csrf_field().'</form>';
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['preview', 'type_badge', 'position_badge', 'schedule', 'status_badge', 'actions'])
            ->make(true);
    }
}
