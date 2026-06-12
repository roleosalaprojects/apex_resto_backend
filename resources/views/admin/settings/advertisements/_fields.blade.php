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
                                   {{ old('media_type', $advertisement->media_type ?? 'image') === 'image' ? 'checked' : '' }}
                                   id="mediaTypeImage">
                            <span class="form-check-label fw-semibold">
                                <i class="fas fa-image text-success me-1"></i> Image
                            </span>
                        </label>
                        <label class="form-check form-check-custom form-check-solid form-check-lg">
                            <input class="form-check-input" type="radio" name="media_type" value="video"
                                   {{ old('media_type', $advertisement->media_type ?? 'image') === 'video' ? 'checked' : '' }}
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
                    <input type="hidden" name="old_media" value="{{ $advertisement->image ?? '' }}" id="oldMediaInput"/>

                    {{-- Image Upload Section --}}
                    <div id="imagePreviewSection" class="{{ old('media_type', $advertisement->media_type ?? 'image') === 'video' ? 'd-none' : '' }}">
                        <div class="border border-dashed border-success rounded p-6 text-center bg-light-success cursor-pointer" id="imageDropzone">
                            <i class="fas fa-image fs-2x text-success mb-3"></i>
                            <div class="fw-bold fs-5 text-gray-700">Drop image here or click to upload</div>
                            <div class="text-muted fs-7 mt-1">JPEG, PNG, JPG formats. Max: 10MB</div>
                            <input type="file" name="media" accept="image/jpeg,image/png,image/jpg" class="d-none" id="imageInput"/>
                        </div>

                        {{-- Current Image Preview --}}
                        @if($advertisement->image && ($advertisement->media_type ?? 'image') === 'image')
                        <div id="currentImagePreview" class="mt-4">
                            <label class="form-label fw-semibold">Current Image</label>
                            <div class="position-relative d-inline-block">
                                <img src="/{{ $advertisement->image }}" alt="Current" class="rounded shadow-sm" style="max-height: 200px; max-width: 100%;">
                            </div>
                        </div>
                        @endif

                        {{-- New Image Preview --}}
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
                    <div id="videoPreviewSection" class="{{ old('media_type', $advertisement->media_type ?? 'image') === 'image' ? 'd-none' : '' }}">
                        <div class="border border-dashed border-primary rounded p-6 text-center bg-light-primary cursor-pointer" id="videoDropzone">
                            <i class="fas fa-video fs-2x text-primary mb-3"></i>
                            <div class="fw-bold fs-5 text-gray-700">Drop video here or click to upload</div>
                            <div class="text-muted fs-7 mt-1">MP4, WebM, MOV formats. Max: 100MB</div>
                            <input type="file" name="media" accept="video/mp4,video/webm,video/quicktime,.mov" class="d-none" id="videoInput"/>
                        </div>

                        {{-- Current Video Preview --}}
                        @if($advertisement->image && ($advertisement->media_type ?? 'image') === 'video')
                        <div id="currentVideoPreview" class="mt-4">
                            <label class="form-label fw-semibold">Current Video</label>
                            <video controls class="w-100 rounded shadow-sm" style="max-height: 200px;">
                                <source src="/{{ $advertisement->image }}" type="video/mp4">
                                Your browser does not support video playback.
                            </video>
                        </div>
                        @endif

                        {{-- New Video Preview --}}
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
            </div>
        </div>
    </div>

    {{-- Right Column - Details --}}
    <div class="col-lg-7">
        <div class="card shadow-sm mb-6">
            <div class="card-header bg-light-info border-0">
                <h3 class="card-title fw-bold text-info">
                    <i class="fas fa-info-circle me-2"></i>Advertisement Details
                </h3>
            </div>
            <div class="card-body">
                {{-- Name --}}
                <div class="mb-6">
                    <label class="form-label required fw-semibold" for="name">Advertisement Name</label>
                    <input type="text" class="form-control form-control-solid" name="name" id="name"
                           value="{{ old('name', $advertisement->name) }}"
                           placeholder="Enter advertisement name">
                    @error('name')
                        <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="mb-6">
                    <label class="form-label fw-semibold" for="description">Description</label>
                    <textarea class="form-control form-control-solid" name="description" id="description"
                              rows="3" placeholder="Brief description (optional)">{{ old('description', $advertisement->description) }}</textarea>
                    @error('description')
                        <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                    @enderror
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
                    {{-- Duration --}}
                    <div class="col-md-6 mb-6">
                        <label class="form-label required fw-semibold" for="duration">
                            <i class="fas fa-clock text-muted me-1"></i>Duration (seconds)
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-solid" name="duration" id="duration"
                                   value="{{ old('duration', $advertisement->duration ?? 10) }}"
                                   min="5" max="300">
                            <span class="input-group-text bg-light">sec</span>
                        </div>
                        <div class="form-text text-muted" id="durationHelp">
                            Image: 5-60 sec | Video: 5-300 sec (5 min)
                        </div>
                        @error('duration')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Display Order --}}
                    <div class="col-md-6 mb-6">
                        <label class="form-label fw-semibold" for="display_order">
                            <i class="fas fa-sort-numeric-down text-muted me-1"></i>Display Order
                        </label>
                        <input type="number" class="form-control form-control-solid" name="display_order" id="display_order"
                               value="{{ old('display_order', $advertisement->display_order ?? $nextOrder ?? 0) }}"
                               min="0" placeholder="0">
                        <div class="form-text text-muted">Lower numbers display first</div>
                        @error('display_order')
                            <div class="text-danger fs-7 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Status --}}
                <div class="mb-6">
                    <label class="form-label required fw-semibold">Status</label>
                    <div class="d-flex gap-6">
                        <label class="form-check form-check-custom form-check-solid form-check-success form-check-lg">
                            <input class="form-check-input" type="radio" name="status" value="1"
                                   {{ old('status', $advertisement->status ?? true) == true ? 'checked' : '' }}>
                            <span class="form-check-label fw-semibold text-success">
                                <i class="fas fa-check-circle me-1"></i> Active
                            </span>
                        </label>
                        <label class="form-check form-check-custom form-check-solid form-check-danger form-check-lg">
                            <input class="form-check-input" type="radio" name="status" value="0"
                                   {{ old('status', $advertisement->status ?? true) == false ? 'checked' : '' }}>
                            <span class="form-check-label fw-semibold text-danger">
                                <i class="fas fa-times-circle me-1"></i> Inactive
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="separator my-6"></div>
                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('advertisements.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i> Cancel
                    </a>
                    <button type="button" id="btnSubmit" class="btn btn-primary">
                        <span class="indicator-label">
                            <i class="fas fa-save me-1"></i> Save Advertisement
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
