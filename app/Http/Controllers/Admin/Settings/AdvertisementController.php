<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\StoreRequest;
use App\Http\Requests\Advertisement\UpdateRequest;
use App\Models\Settings\Advertisement;

class AdvertisementController extends Controller
{
    private string $mediaLocation = 'advertisements';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.settings.advertisements.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $advertisement = new Advertisement;
        $nextOrder = Advertisement::max('display_order') + 1;

        return view('admin.settings.advertisements.create', compact('advertisement', 'nextOrder'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();

        $helper = new HelperController;
        $mediaPath = $helper->uploadMedia($request, $this->mediaLocation);

        $advertisement = Advertisement::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $mediaPath,
            'media_type' => $validated['media_type'],
            'duration' => $validated['duration'],
            'status' => $validated['status'],
            'display_order' => $validated['display_order'] ?? 0,
        ]);

        return redirect()
            ->route('advertisements.index')
            ->with('success', 'Successfully added a new Advertisement!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Advertisement $advertisement)
    {
        return view('admin.settings.advertisements.show', compact('advertisement'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Advertisement $advertisement)
    {
        return view('admin.settings.advertisements.edit', compact('advertisement'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request, Advertisement $advertisement)
    {
        $validated = $request->validated();

        $helper = new HelperController;

        $mediaPath = $request->hasFile('media')
            ? $helper->uploadMedia($request, $this->mediaLocation)
            : $validated['old_media'];

        $advertisement->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $mediaPath,
            'media_type' => $validated['media_type'],
            'duration' => $validated['duration'],
            'status' => $validated['status'],
            'display_order' => $validated['display_order'] ?? 0,
        ]);

        return redirect()
            ->route('advertisements.index')
            ->with('info', 'Advertisement updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Advertisement $advertisement)
    {
        $advertisement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully deleted advertisement!',
        ]);
    }

    /**
     * Get advertisements for DataTables.
     */
    public function table()
    {
        $query = Advertisement::query()->orderBy('display_order');

        return datatables($query)
            ->addColumn('preview', function (Advertisement $advertisement) {
                if ($advertisement->isVideo()) {
                    return '<div class="symbol symbol-50px">
                        <div class="symbol-label bg-light-primary">
                            <i class="fas fa-video text-primary fs-3"></i>
                        </div>
                    </div>';
                }

                $imageUrl = $advertisement->image ?: '/assets/media/svg/general/rhone.svg';

                return '<div class="symbol symbol-50px">
                    <div class="symbol-label" style="background-image:url(\'/'.$imageUrl.'\')"></div>
                </div>';
            })
            ->addColumn('type_badge', function (Advertisement $advertisement) {
                $badge = $advertisement->isVideo()
                    ? '<span class="badge badge-light-primary">Video</span>'
                    : '<span class="badge badge-light-success">Image</span>';

                return $badge;
            })
            ->addColumn('duration_formatted', function (Advertisement $advertisement) {
                $minutes = floor($advertisement->duration / 60);
                $seconds = $advertisement->duration % 60;

                if ($minutes > 0) {
                    return sprintf('%dm %ds', $minutes, $seconds);
                }

                return sprintf('%ds', $seconds);
            })
            ->addColumn('status_badge', function (Advertisement $advertisement) {
                return $advertisement->status
                    ? '<span class="badge badge-light-success">Active</span>'
                    : '<span class="badge badge-light-danger">Inactive</span>';
            })
            ->addColumn('actions', function (Advertisement $advertisement) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                $action .= '<a href="'.route('advertisements.show', $advertisement->id).'"
                        class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Details">
                        <i class="fas fa-eye"></i>
                    </a>&nbsp;';
                $action .= '<a href="'.route('advertisements.edit', $advertisement->id).'"
                    class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1"
                    value="'.$advertisement->id.'"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="Edit">
                    <i class="fas fa-edit"></i>
                </a>&nbsp;';
                $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$advertisement->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                $action .= '<input type="hidden" id="name_'.$advertisement->id.'" value="'.$advertisement->name.'" />';
                $action .= '<form method="POST" action="'.route('advertisements.destroy', $advertisement->id).'" id="form_delete_'.$advertisement->id.'" value="'.$advertisement->name.'">'.method_field('DELETE').csrf_field().'</form>';
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['preview', 'type_badge', 'status_badge', 'actions'])
            ->make(true);
    }
}
