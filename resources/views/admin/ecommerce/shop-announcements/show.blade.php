@extends('layout.app')
@section('header')
    - View Shop Announcement
@endsection
@section('title')
    Shop Announcements
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('shop-announcements.index') }}">Shop Announcements</a></li>
    <li class="breadcrumb-item pe-3 text-muted">View</li>
@endsection
@section('actions')
    <a href="{{ route('shop-announcements.edit', $announcement) }}" class="btn btn-primary">
        <i class="fas fa-edit me-1"></i> Edit
    </a>
@endsection
@section('content')
    <div class="row">
        {{-- Media Preview --}}
        <div class="col-lg-5 mb-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light-primary border-0">
                    <h3 class="card-title fw-bold text-primary">
                        <i class="fas fa-photo-video me-2"></i>Media Preview
                    </h3>
                </div>
                <div class="card-body text-center">
                    @if($announcement->media_path)
                        @if($announcement->isVideo())
                            <video controls class="w-100 rounded shadow-sm" style="max-height: 400px;">
                                <source src="/{{ $announcement->media_path }}" type="video/mp4">
                                Your browser does not support video playback.
                            </video>
                        @else
                            <img src="/{{ $announcement->media_path }}" alt="{{ $announcement->title }}" class="rounded shadow-sm" style="max-height: 400px; max-width: 100%;">
                        @endif
                    @else
                        <div class="py-10 text-muted">
                            <i class="fas fa-image fs-3x mb-3"></i>
                            <p>No media uploaded</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="col-lg-7">
            <div class="card shadow-sm mb-6">
                <div class="card-header bg-light-info border-0">
                    <h3 class="card-title fw-bold text-info">
                        <i class="fas fa-info-circle me-2"></i>Announcement Details
                    </h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <tr>
                            <th class="text-muted w-25">Title</th>
                            <td class="fw-bold">{{ $announcement->title }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Description</th>
                            <td>{{ $announcement->description ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Media Type</th>
                            <td>
                                @if($announcement->isVideo())
                                    <span class="badge badge-light-primary">Video</span>
                                @else
                                    <span class="badge badge-light-success">Image</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Link URL</th>
                            <td>
                                @if($announcement->link_url)
                                    <a href="{{ $announcement->link_url }}" target="_blank">{{ $announcement->link_url }}</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Button Text</th>
                            <td>{{ $announcement->link_text ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light-warning border-0">
                    <h3 class="card-title fw-bold text-warning">
                        <i class="fas fa-cog me-2"></i>Display Settings
                    </h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <tr>
                            <th class="text-muted w-25">Position</th>
                            <td>
                                @php
                                    $positionBadges = [
                                        'hero' => '<span class="badge badge-light-info">Hero Carousel</span>',
                                        'banner' => '<span class="badge badge-light-warning">Banner</span>',
                                        'popup' => '<span class="badge badge-light-danger">Popup</span>',
                                    ];
                                @endphp
                                {!! $positionBadges[$announcement->position] ?? $announcement->position !!}
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Display Order</th>
                            <td>{{ $announcement->display_order }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Status</th>
                            <td>
                                @if($announcement->isCurrentlyActive())
                                    <span class="badge badge-light-success">Active</span>
                                @elseif(!$announcement->is_active)
                                    <span class="badge badge-light-danger">Inactive</span>
                                @else
                                    <span class="badge badge-light-warning">Scheduled</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Start Date</th>
                            <td>{{ $announcement->starts_at?->format('M d, Y h:i A') ?: 'Immediate' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">End Date</th>
                            <td>{{ $announcement->ends_at?->format('M d, Y h:i A') ?: 'Indefinite' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Created</th>
                            <td>{{ $announcement->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Updated</th>
                            <td>{{ $announcement->updated_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
