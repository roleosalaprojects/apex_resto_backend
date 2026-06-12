@extends('layout.app')
@section('title')
    Branding
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Branding</li>
@endsection
@section('content')
    {{-- Flash banners are rendered by layout/messages.blade.php. --}}

    <form action="{{ route('admin.settings.branding.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-5">
            {{-- Brand identity --}}
            <div class="col-xl-5">
                <div class="card card-flush mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Brand identity</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-7">Logo, brand name, and a live preview</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <div class="mb-5">
                            <label for="brand_name" class="form-label">Brand name</label>
                            <input type="text" class="form-control" id="brand_name" name="brand_name"
                                   value="{{ old('brand_name', $setting?->brand_name) }}"
                                   placeholder="APEX" maxlength="60">
                            <div class="form-text">Replaces "APEX" on the navbar and in page titles. Leave blank to keep the default.</div>
                            @error('brand_name')<div class="text-danger fs-7 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-5">
                            <label class="form-label d-block">Logo</label>

                            <style>
                                .logo-input-placeholder {
                                    background-image: url('{{asset("/assets/media/svg/shapes/abstract-4.svg")}}');
                                }
                                [data-bs-theme="dark"] .logo-input-placeholder {
                                    background-image: url('{{asset("/assets/media/svg/shapes/abstract-4-dark.svg")}}');
                                }
                                .logo-input .image-input-wrapper {
                                    background-size: contain !important;
                                    background-repeat: no-repeat !important;
                                    background-position: center !important;
                                }
                            </style>

                            <div class="image-input image-input-outline logo-input mb-3"
                                 data-kt-image-input="true"
                                 style="background-image: url({{asset('/assets/media/svg/shapes/abstract-4.svg')}})">
                                <div class="image-input-wrapper w-250px h-130px logo-input-placeholder"
                                     @if ($setting?->logo_path)
                                         style="background-image: url({{ \Illuminate\Support\Facades\Storage::disk('public')->url($setting->logo_path) }})"
                                     @endif></div>

                                <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                       data-kt-image-input-action="change"
                                       data-bs-toggle="tooltip"
                                       title="Change logo">
                                    <i class="fa-solid fa-pencil"></i>
                                    <input type="file" name="logo" id="logo" accept=".png,.jpg,.jpeg,.webp"/>
                                </label>

                                <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                      data-kt-image-input-action="cancel"
                                      data-bs-toggle="tooltip"
                                      title="Cancel">
                                    <i class="fa-solid fa-xmark"></i>
                                </span>

                                <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-danger w-25px h-25px bg-body shadow"
                                      data-kt-image-input-action="remove"
                                      data-bs-toggle="tooltip"
                                      title="Remove logo"
                                      onclick="document.getElementById('removeLogoFlag').value='1';">
                                    <i class="fa-solid fa-trash"></i>
                                </span>
                            </div>

                            <input type="hidden" name="remove_logo" id="removeLogoFlag" value="0">

                            <div class="form-text">PNG, JPG, or WEBP. Max 500KB, 1200×400 px. SVG is not allowed.</div>
                            @error('logo')<div class="text-danger fs-7 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="separator border-gray-200 my-5"></div>

                        <div class="text-muted fs-7 mb-2">Preview</div>
                        <div id="brandingPreview" class="p-4 rounded bg-light-info">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div id="previewLogo" style="display:inline-flex;align-items:center;gap:8px;">
                                    @if ($setting?->logo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($setting->logo_path) }}"
                                             alt="Brand" style="max-height: 32px;">
                                    @endif
                                    <span id="previewBrandName" class="fw-bold fs-4 text-gray-900">{{ $setting?->brand_name ?: 'APEX' }}</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm" id="previewPrimary">Primary action</button>
                            <button type="button" class="btn btn-sm ms-2" id="previewSecondary">Secondary</button>
                            <span class="ms-2 fw-bold" id="previewAccent">Accent</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Palette picker --}}
            <div class="col-xl-7">
                <div class="card card-flush">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Color palette</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-7">Curated by your platform administrator</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        @error('palette_key')<div class="alert alert-danger fs-7">{{ $message }}</div>@enderror

                        <div class="row g-3">
                            @foreach ($palettes as $palette)
                                @php
                                    $selected = old('palette_key', $setting?->palette_key ?? $palettes->firstWhere('is_default', true)?->key) === $palette->key;
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <label class="palette-card d-block p-3 rounded border {{ $selected ? 'border-primary border-2' : 'border-gray-300' }}"
                                           style="cursor:pointer;"
                                           data-palette-key="{{ $palette->key }}"
                                           data-primary="{{ $palette->primary }}"
                                           data-secondary="{{ $palette->secondary }}"
                                           data-accent="{{ $palette->accent }}"
                                           data-on-primary="{{ $palette->on_primary }}"
                                           data-on-secondary="{{ $palette->on_secondary }}">
                                        <input class="form-check-input visually-hidden" type="radio"
                                               name="palette_key" value="{{ $palette->key }}"
                                               {{ $selected ? 'checked' : '' }}>
                                        <div class="fw-bold text-gray-800 mb-2">
                                            {{ $palette->label }}
                                            @if ($palette->is_default)
                                                <span class="badge bg-light text-muted ms-1">default</span>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-1">
                                            <span style="display:inline-block;width:28px;height:28px;border-radius:4px;background: {{ $palette->primary }}"></span>
                                            <span style="display:inline-block;width:28px;height:28px;border-radius:4px;background: {{ $palette->secondary }}"></span>
                                            <span style="display:inline-block;width:28px;height:28px;border-radius:4px;background: {{ $palette->accent }}"></span>
                                            <span style="display:inline-block;width:28px;height:28px;border-radius:4px;background: {{ $palette->on_primary }};border:1px solid #e5e7eb"></span>
                                            <span style="display:inline-block;width:28px;height:28px;border-radius:4px;background: {{ $palette->on_secondary }};border:1px solid #e5e7eb"></span>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-5">
            <button type="submit" class="btn btn-primary">Save branding</button>
        </div>
    </form>
@endsection
@section('scripts')
    <script>
        (function () {
            const cards = document.querySelectorAll('.palette-card');
            const brandNameInput = document.getElementById('brand_name');
            const previewBrand = document.getElementById('previewBrandName');
            const previewPrimary = document.getElementById('previewPrimary');
            const previewSecondary = document.getElementById('previewSecondary');
            const previewAccent = document.getElementById('previewAccent');
            const removeLogoFlag = document.getElementById('removeLogoFlag');
            const logoInput = document.getElementById('logo');

            // Picking a new file overrides any prior "remove" click.
            logoInput?.addEventListener('change', () => {
                if (logoInput.files && logoInput.files.length > 0) {
                    removeLogoFlag.value = '0';
                }
            });

            function applyPalette(card) {
                if (!card) return;
                cards.forEach(c => c.classList.remove('border-primary', 'border-2'));
                cards.forEach(c => c.classList.add('border-gray-300'));
                card.classList.add('border-primary', 'border-2');
                card.classList.remove('border-gray-300');
                card.querySelector('input[type=radio]').checked = true;
                previewPrimary.style.backgroundColor = card.dataset.primary;
                previewPrimary.style.color = card.dataset.onPrimary;
                previewPrimary.style.border = 'none';
                previewSecondary.style.backgroundColor = card.dataset.secondary;
                previewSecondary.style.color = card.dataset.onSecondary;
                previewSecondary.style.border = 'none';
                previewAccent.style.color = card.dataset.accent;
            }

            cards.forEach(card => {
                card.addEventListener('click', () => applyPalette(card));
            });

            const selected = document.querySelector('.palette-card input[type=radio]:checked')?.closest('.palette-card');
            if (selected) applyPalette(selected);

            brandNameInput?.addEventListener('input', () => {
                previewBrand.textContent = brandNameInput.value.trim() || 'APEX';
            });
        })();
    </script>
@endsection
