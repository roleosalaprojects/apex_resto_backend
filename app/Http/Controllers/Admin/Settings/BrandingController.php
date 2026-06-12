<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateBrandingRequest;
use App\Models\Settings\BrandingSetting;
use App\Models\Settings\ColorPalette;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandingController extends Controller
{
    public function show(): View
    {
        $tenantUserId = (int) auth()->user()->user_id;

        $setting = BrandingSetting::query()
            ->where('user_id', $tenantUserId)
            ->first();

        $palettes = ColorPalette::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('admin.settings.branding.index', compact('setting', 'palettes'));
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;
        $data = $request->validated();

        $setting = BrandingSetting::query()->firstOrNew(['user_id' => $tenantUserId]);
        $setting->palette_key = $data['palette_key'];
        $setting->brand_name = ($data['brand_name'] ?? null) ?: null;

        if ($request->boolean('remove_logo') && $setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $setting->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            $newPath = $this->storeUploadedLogo(
                tenantUserId: $tenantUserId,
                file: $request->file('logo'),
            );

            // Drop the old file once the new one is safely on disk.
            if ($setting->logo_path && $setting->logo_path !== $newPath) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            $setting->logo_path = $newPath;
        }

        $setting->save();

        return redirect()
            ->route('admin.settings.branding.show')
            ->with('success', 'Branding updated.');
    }

    /**
     * Re-encode the uploaded image via GD before persisting. This destroys
     * any polyglot payload (a file that's valid PNG AND valid JS/HTML) and
     * normalises the output to the requested extension.
     */
    private function storeUploadedLogo(int $tenantUserId, \Illuminate\Http\UploadedFile $file): string
    {
        $directory = "branding/{$tenantUserId}";
        Storage::disk('public')->makeDirectory($directory);

        $extension = match (strtolower($file->getClientOriginalExtension())) {
            'jpg', 'jpeg' => 'jpg',
            'webp' => 'webp',
            default => 'png',
        };

        $filename = Str::random(40).'.'.$extension;
        $fullPath = Storage::disk('public')->path("{$directory}/{$filename}");

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($image === false) {
            throw new \RuntimeException('Uploaded file could not be decoded as an image.');
        }

        match ($extension) {
            'jpg' => imagejpeg($image, $fullPath, 90),
            'webp' => imagewebp($image, $fullPath, 90),
            default => imagepng($image, $fullPath, 6),
        };
        imagedestroy($image);

        return "{$directory}/{$filename}";
    }
}
