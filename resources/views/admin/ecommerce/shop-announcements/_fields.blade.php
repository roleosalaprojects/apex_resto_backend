@csrf
<div class="row">
    {{-- Left Column - Media Upload --}}
    <div class="col-lg-5 mb-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light-primary border-0">
                <h3 class="card-title fw-bold text-primary">
                    <i class="fas fa-photo-video me-2"></i>Media Upload
                </h3>
            </div>
            <div class="card-body">
                {{-- Media Type Selection --}}
                <div class="mb-6">
                    <label class="form-label required fw-semibold">Media Type</label>
                    <div class="d-flex gap-4">
                        <label class="form-check form-check-custom form-check-solid form-check-lg">
                            <input class="form-check-input" type="radio" name="media_type" value="image"
                                   {{ old('media_type', $announcement->media_type ?? 'image') === 'image' ? 'checked' : '' }}
                                   id="mediaTypeImage">
                            <span class="form-check-label fw-semibold">
                                <i class="fas fa-image text-success me-1"></i> Image
                            </span>
                        </label>
                        <label class="form-check form-check-custom form-check-solid form-check-lg">
                            <input class="form-check-input" type="radio" name="media_type" value="video"
                                   {{ old('media_type', $announcement->media_type ?? 'image') === 'video' ? 'checked' : '' }}
                                   id="mediaTypeVideo">
                            <span class="form-check-label fw-semibold">
                                <i class="fas fa-video text-primary me-1"></i> Video
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Media Preview & Upload --}}
                <div class="mb-4">
                    <label class="form-label required fw-semibold">Upload Media</label>
                    <input type="hidden" name="old_media" value="{{ $announcement->media_path ?? '' }}" id="oldMediaInput"/>

                    {{-- Image Upload Section --}}
                    <div id="imagePreviewSection" class="{{ old('media_type', $announcement->media_type ?? 'image') === 'video' ? 'd-none' : '' }}">
                        <div class="border border-dashed border-success rounded p-6 text-center bg-light-success cursor-pointer" id="imageDropzone">
                            <i class="fas fa-image fs-2x text-success mb-3"></i>
                            <div class="fw-bold fs-5 text-gray-700">Drop image here or click to upload</div>
                            <div class="text-muted fs-7 mt-1">JPEG, PNG, JPG, GIF formats. Max: 10MB</div>
                            <input type="file" name="media" accept="image/jpeg,image/png,image/jpg,image/gif" class="d-none" id="imageInput"/>
                        </div>

                        @if($announcement->media_path && ($announcement->media_type ?? 'image') === 'image')
                        <div id="currentImagePreview" class="mt-4">
                            <label class="form-label fw-semibold">Current Image</label>
                            <div class="position-relative d-inline-block">
                                <img src="/{{ $announcement->media_path }}" alt="Current" class="rounded shadow-sm" style="max-height: 200px; max-width: 100%;">
                            </div>
                        </div>
                        @endif

                        <div id="newImagePreview" class="mt-4 d-none">
                            <label class="form-label fw-semibold">New Image Preview</label>
                            <div class="position-relative">
                                <img id="imagePreviewImg" src="" alt="Preview" class="rounded shadow-sm" style="max-height: 200px; max-width: 100%;">
                                <button type="button" class="btn btn-sm btn-light-danger mt-2" id="removeImageBtn">
                                    <i class="fas fa-times me-1"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Video Upload Section --}}
                    <div id="videoPreviewSection" class="{{ old('media_type', $announcement->media_type ?? 'image') === 'image' ? 'd-none' : '' }}">
                        <div class="border border-dashed border-primary rounded p-6 text-center bg-light-primary cursor-pointer" id="videoDropzone">
                            <i class="fas fa-video fs-2x text-primary mb-3"></i>
                            <div class="fw-bold fs-5 text-gray-700">Drop video here or click to upload</div>
                            <div class="text-muted fs-7 mt-1">MP4, WebM, MOV formats. Max: 100MB</div>
                            <input type="file" name="media" accept="video/mp4,video/webm,video/quicktime,.mov" class="d-none" id="videoInput"/>
                        </div>

                        @if($announcement->media_path && ($announcement->media_type ?? 'image') === 'video')
                        <div id="currentVideoPreview" class="mt-4">
                            <label class="form-label fw-semibold">Current Video</label>
                            <video controls class="w-100 rounded shadow-sm" style="max-height: 200px;">
                                <source src="/{{ $announcement->media_path }}" type="video/mp4">
                                Your browser does not support video playback.
                            </video>
                        </div>
                        @endif

                        <div id="newVideoPreview" class="mt-4 d-none">
                            <label class="form-label fw-semibold">New Video Preview</label>
                            <video controls class="w-100 rounded shadow-sm" style="max-height: 200px;" id="videoPreviewPlayer">
                                Your browser does not support video playback.
                            </video>
                            <button type="button" class="btn btn-sm btn-light-danger mt-2" id="removeVideoBtn">
                                <i class="fas fa-times me-1"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
                @error('media')
                    <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- Right Column - Details --}}
    <div class="col-lg-7">
        <div class="card shadow-sm mb-6">
            <div class="card-header bg-light-info border-0">
                <h3 class="card-title fw-bold text-info">
                    <i class="fas fa-info-circle me-2"></i>Announcement Details
                </h3>
            </div>
            <div class="card-body">
                {{-- Title --}}
                <div class="mb-6">
                    <label class="form-label required fw-semibold" for="title">Title</label>
                    <input type="text" class="form-control form-control-solid" name="title" id="title"
                           value="{{ old('title', $announcement->title) }}"
                           placeholder="Enter announcement title">
                    @error('title')
                        <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="mb-6">
                    <label class="form-label fw-semibold" for="description">Description</label>
                    <textarea class="form-control form-control-solid" name="description" id="description"
                              rows="3" placeholder="Brief description or tagline (optional)">{{ old('description', $announcement->description) }}</textarea>
                    @error('description')
                        <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    {{-- Link URL --}}
                    <div class="col-md-8 mb-6">
                        <label class="form-label fw-semibold" for="link_url">
                            <i class="fas fa-link text-muted me-1"></i>Link URL
                        </label>
                        <input type="url" class="form-control form-control-solid" name="link_url" id="link_url"
                               value="{{ old('link_url', $announcement->link_url) }}"
                               placeholder="https://example.com/promo">
                        @error('link_url')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Link Text --}}
                    <div class="col-md-4 mb-6">
                        <label class="form-label fw-semibold" for="link_text">Button Text</label>
                        <input type="text" class="form-control form-control-solid" name="link_text" id="link_text"
                               value="{{ old('link_text', $announcement->link_text) }}"
                               placeholder="Shop Now">
                        @error('link_text')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light-warning border-0">
                <h3 class="card-title fw-bold text-warning">
                    <i class="fas fa-cog me-2"></i>Display Settings
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    {{-- Position --}}
                    <div class="col-md-4 mb-6">
                        <label class="form-label required fw-semibold" for="position">
                            <i class="fas fa-map-marker-alt text-muted me-1"></i>Position
                        </label>
                        <select class="form-select form-select-solid" name="position" id="position">
                            <option value="hero" {{ old('position', $announcement->position ?? 'hero') === 'hero' ? 'selected' : '' }}>Hero Carousel</option>
                            <option value="banner" {{ old('position', $announcement->position) === 'banner' ? 'selected' : '' }}>Banner</option>
                            <option value="popup" {{ old('position', $announcement->position) === 'popup' ? 'selected' : '' }}>Popup</option>
                        </select>
                        @error('position')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Display Order --}}
                    <div class="col-md-4 mb-6">
                        <label class="form-label fw-semibold" for="display_order">
                            <i class="fas fa-sort-numeric-down text-muted me-1"></i>Display Order
                        </label>
                        <input type="number" class="form-control form-control-solid" name="display_order" id="display_order"
                               value="{{ old('display_order', $announcement->display_order ?? $nextOrder ?? 0) }}"
                               min="0" placeholder="0">
                        <div class="form-text text-muted">Lower numbers display first</div>
                        @error('display_order')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div class="col-md-4 mb-6">
                        <label class="form-label required fw-semibold">Status</label>
                        <div class="d-flex gap-4 mt-2">
                            <label class="form-check form-check-custom form-check-solid form-check-success">
                                <input class="form-check-input" type="radio" name="is_active" value="1"
                                       {{ old('is_active', $announcement->is_active ?? true) == true ? 'checked' : '' }}>
                                <span class="form-check-label fw-semibold text-success">Active</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid form-check-danger">
                                <input class="form-check-input" type="radio" name="is_active" value="0"
                                       {{ old('is_active', $announcement->is_active ?? true) == false ? 'checked' : '' }}>
                                <span class="form-check-label fw-semibold text-danger">Inactive</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Schedule --}}
                <div class="row">
                    <div class="col-md-6 mb-6">
                        <label class="form-label fw-semibold" for="starts_at">
                            <i class="fas fa-calendar-alt text-muted me-1"></i>Start Date (Optional)
                        </label>
                        <input type="datetime-local" class="form-control form-control-solid" name="starts_at" id="starts_at"
                               value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text text-muted">Leave empty to start immediately</div>
                        @error('starts_at')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-6">
                        <label class="form-label fw-semibold" for="ends_at">
                            <i class="fas fa-calendar-times text-muted me-1"></i>End Date (Optional)
                        </label>
                        <input type="datetime-local" class="form-control form-control-solid" name="ends_at" id="ends_at"
                               value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text text-muted">Leave empty to run indefinitely</div>
                        @error('ends_at')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="separator my-6"></div>
                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('shop-announcements.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i> Cancel
                    </a>
                    <button type="button" id="btnSubmit" class="btn btn-primary">
                        <span class="indicator-label">
                            <i class="fas fa-save me-1"></i> Save Announcement
                        </span>
                        <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
