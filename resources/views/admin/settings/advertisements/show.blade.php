@extends('layout.app')
@section('header')
    - View Advertisement
@endsection
@section('title')
    {{ $advertisement->name }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('advertisements.index') }}">Advertisements</a></li>
    <li class="breadcrumb-item pe-3 text-muted">{{ $advertisement->name }}</li>
@endsection
@section('actions')
    <a href="{{ route('advertisements.edit', $advertisement->id) }}" class="btn btn-light-primary">
        <i class="fas fa-edit me-1"></i> Edit
    </a>
@endsection
@section('content')
    <div class="row">
        {{-- Left Column - Media Preview --}}
        <div class="col-lg-5 mb-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light-primary border-0">
                    <h3 class="card-title fw-bold text-primary">
                        @if($advertisement->isVideo())
                            <i class="fas fa-video me-2"></i>Video Preview
                        @else
                            <i class="fas fa-image me-2"></i>Image Preview
                        @endif
                    </h3>
                    <div class="card-toolbar">
                        @if($advertisement->isVideo())
                            <span class="badge badge-light-primary fs-7">Video</span>
                        @else
                            <span class="badge badge-light-success fs-7">Image</span>
                        @endif
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center p-6">
                    @if($advertisement->isVideo())
                        <video controls class="w-100 rounded shadow" style="max-height: 400px;">
                            <source src="/{{ $advertisement->image }}" type="video/mp4">
                            Your browser does not support video playback.
                        </video>
                    @else
                        <img src="/{{ $advertisement->image ?: 'assets/media/svg/general/rhone.svg' }}"
                             alt="{{ $advertisement->name }}"
                             class="img-fluid rounded shadow"
                             style="max-height: 400px; object-fit: contain;">
                    @endif
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
                    <div class="d-flex flex-stack mb-6">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-4">
                                <div class="symbol-label bg-light-primary">
                                    <i class="fas fa-tag text-primary fs-4"></i>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted fs-7 d-block">Name</span>
                                <span class="fw-bold fs-5">{{ $advertisement->name }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    @if($advertisement->description)
                    <div class="d-flex flex-stack mb-6">
                        <div class="d-flex align-items-start">
                            <div class="symbol symbol-45px me-4">
                                <div class="symbol-label bg-light-warning">
                                    <i class="fas fa-align-left text-warning fs-4"></i>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted fs-7 d-block">Description</span>
                                <span class="fw-semibold">{{ $advertisement->description }}</span>
                            </div>
                        </div>
                    </div>
                    @endif
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
                        {{-- Media Type --}}
                        <div class="col-md-6 mb-6">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-4">
                                    <div class="symbol-label {{ $advertisement->isVideo() ? 'bg-light-primary' : 'bg-light-success' }}">
                                        <i class="fas {{ $advertisement->isVideo() ? 'fa-video text-primary' : 'fa-image text-success' }} fs-4"></i>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-muted fs-7 d-block">Media Type</span>
                                    <span class="fw-bold fs-5">{{ ucfirst($advertisement->media_type) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Duration --}}
                        <div class="col-md-6 mb-6">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-4">
                                    <div class="symbol-label bg-light-info">
                                        <i class="fas fa-clock text-info fs-4"></i>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-muted fs-7 d-block">Duration</span>
                                    @php
                                        $minutes = floor($advertisement->duration / 60);
                                        $seconds = $advertisement->duration % 60;
                                        $durationText = $minutes > 0 ? sprintf('%dm %ds', $minutes, $seconds) : sprintf('%d seconds', $seconds);
                                    @endphp
                                    <span class="fw-bold fs-5">{{ $durationText }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Display Order --}}
                        <div class="col-md-6 mb-6">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-4">
                                    <div class="symbol-label bg-light-dark">
                                        <i class="fas fa-sort-numeric-down text-dark fs-4"></i>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-muted fs-7 d-block">Display Order</span>
                                    <span class="fw-bold fs-5">#{{ $advertisement->display_order }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="col-md-6 mb-6">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-4">
                                    <div class="symbol-label {{ $advertisement->status ? 'bg-light-success' : 'bg-light-danger' }}">
                                        <i class="fas {{ $advertisement->status ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' }} fs-4"></i>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-muted fs-7 d-block">Status</span>
                                    @if($advertisement->status)
                                        <span class="badge badge-light-success fs-6">Active</span>
                                    @else
                                        <span class="badge badge-light-danger fs-6">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Timestamps --}}
                    <div class="separator my-5"></div>
                    <div class="d-flex justify-content-between text-muted fs-7">
                        <span><i class="fas fa-calendar-plus me-1"></i>Created: {{ $advertisement->created_at->format('M d, Y h:i A') }}</span>
                        <span><i class="fas fa-calendar-check me-1"></i>Updated: {{ $advertisement->updated_at->format('M d, Y h:i A') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
