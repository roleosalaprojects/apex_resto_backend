<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreColorPaletteRequest;
use App\Http\Requests\SuperAdmin\UpdateColorPaletteRequest;
use App\Models\Settings\ColorPalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ColorPaletteController extends Controller
{
    public function index(): View
    {
        return view('superadmin.color-palettes.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = ColorPalette::query()->orderBy('sort_order')->orderBy('label');

        return DataTables::of($query)
            ->addColumn('swatch', fn (ColorPalette $p) => implode(',', [
                $p->primary, $p->secondary, $p->accent, $p->on_primary, $p->on_secondary,
            ]))
            ->editColumn('is_default', fn (ColorPalette $p) => $p->is_default ? 1 : 0)
            ->editColumn('is_active', fn (ColorPalette $p) => $p->is_active ? 1 : 0)
            ->make(true);
    }

    public function create(): View
    {
        return view('superadmin.color-palettes.create', [
            'palette' => new ColorPalette([
                'primary' => '#1858fd',
                'secondary' => '#1652ea',
                'accent' => '#f6a623',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'is_active' => true,
                'sort_order' => 100,
            ]),
        ]);
    }

    public function store(StoreColorPaletteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_default'] = false; // new palettes never start as default

        ColorPalette::create($this->normalizeHex($data));

        return redirect()
            ->route('superadmin.color-palettes.index')
            ->with('success', 'Color palette created.');
    }

    public function edit(ColorPalette $palette): View
    {
        return view('superadmin.color-palettes.edit', compact('palette'));
    }

    public function update(UpdateColorPaletteRequest $request, ColorPalette $palette): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $palette->is_active);
        $data['sort_order'] = $data['sort_order'] ?? $palette->sort_order;

        $palette->update($this->normalizeHex($data));

        return redirect()
            ->route('superadmin.color-palettes.index')
            ->with('success', 'Color palette updated.');
    }

    public function destroy(ColorPalette $palette): RedirectResponse
    {
        if ($palette->is_default) {
            return redirect()
                ->route('superadmin.color-palettes.index')
                ->with('error', 'The default palette cannot be deleted.');
        }

        $palette->delete();

        return redirect()
            ->route('superadmin.color-palettes.index')
            ->with('success', 'Color palette deleted.');
    }

    public function setDefault(ColorPalette $palette): RedirectResponse
    {
        if (! $palette->is_active) {
            return redirect()
                ->route('superadmin.color-palettes.index')
                ->with('error', 'An inactive palette cannot be set as default.');
        }

        DB::transaction(function () use ($palette) {
            $palette->update(['is_default' => true]);
        });

        return redirect()
            ->route('superadmin.color-palettes.index')
            ->with('success', "‘{$palette->label}’ is now the default palette.");
    }

    public function toggleActive(ColorPalette $palette): RedirectResponse
    {
        if ($palette->is_default && $palette->is_active) {
            return redirect()
                ->route('superadmin.color-palettes.index')
                ->with('error', 'The default palette cannot be deactivated.');
        }

        $palette->update(['is_active' => ! $palette->is_active]);

        return redirect()
            ->route('superadmin.color-palettes.index')
            ->with('success', $palette->is_active ? 'Palette activated.' : 'Palette deactivated.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeHex(array $data): array
    {
        foreach (['primary', 'secondary', 'accent', 'on_primary', 'on_secondary'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = strtolower($data[$key]);
            }
        }

        return $data;
    }
}
