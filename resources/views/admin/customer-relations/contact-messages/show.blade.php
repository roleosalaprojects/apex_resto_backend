@extends('layout.app')
@section('header')
    - Contact Message
@endsection
@section('title')
    Contact Message
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('contact-messages.index') }}">Contact Messages</a></li>
    <li class="breadcrumb-item pe-3 text-muted">View</li>
@endsection
@section('actions')
    <div class="d-flex gap-2">
        @if ($contactMessage->status !== 'replied')
            <button type="button" class="btn btn-success btn-sm" id="btnMarkReplied">
                <i class="fas fa-check me-1"></i> Mark as Replied
            </button>
        @endif
        @if ($contactMessage->status !== 'archived')
            <button type="button" class="btn btn-secondary btn-sm" id="btnArchive">
                <i class="fas fa-archive me-1"></i> Archive
            </button>
        @endif
        <a href="mailto:{{ $contactMessage->email }}?subject=Re: {{ urlencode($contactMessage->subject) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-reply me-1"></i> Reply via Email
        </a>
    </div>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row mb-8">
                <div class="col-md-6">
                    <div class="mb-5">
                        <label class="fw-bold text-muted fs-7">Sender Name</label>
                        <div class="fs-5 fw-semibold">{{ $contactMessage->name }}</div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-bold text-muted fs-7">Email Address</label>
                        <div class="fs-5">
                            <a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a>
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-bold text-muted fs-7">Subject</label>
                        <div class="fs-5 fw-semibold">
                            @php
                                $subjectLabels = [
                                    'web-development' => 'Web Development',
                                    'mobile-app' => 'Mobile App',
                                    'pos-system' => 'POS System',
                                    'api-development' => 'API Development',
                                    'database-design' => 'Database Design',
                                    'tech-support' => 'Tech Support',
                                    'other' => 'Other',
                                ];
                            @endphp
                            {{ $subjectLabels[$contactMessage->subject] ?? $contactMessage->subject }}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-5">
                        <label class="fw-bold text-muted fs-7">Status</label>
                        <div>
                            @php
                                $badges = [
                                    'pending' => 'badge-light-warning',
                                    'read' => 'badge-light-info',
                                    'replied' => 'badge-light-success',
                                    'archived' => 'badge-light-secondary',
                                ];
                                $statusLabels = [
                                    'pending' => 'Unread',
                                    'read' => 'Read',
                                    'replied' => 'Replied',
                                    'archived' => 'Archived',
                                ];
                            @endphp
                            <span class="badge {{ $badges[$contactMessage->status] ?? 'badge-light' }}">
                                {{ $statusLabels[$contactMessage->status] ?? $contactMessage->status }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-bold text-muted fs-7">Received</label>
                        <div class="fs-5">{{ $contactMessage->created_at->format('M d, Y h:i A') }}</div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-bold text-muted fs-7">IP Address</label>
                        <div class="fs-7 text-muted">{{ $contactMessage->ip_address }}</div>
                    </div>
                </div>
            </div>

            <div class="separator my-6"></div>

            <div>
                <label class="fw-bold text-muted fs-7 mb-3">Message</label>
                <div class="p-5 bg-light rounded fs-5" style="white-space: pre-wrap;">{{ $contactMessage->message }}</div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
$(function() {
    $('#btnMarkReplied').on('click', function() {
        $.ajax({
            url: '{{ route("contact-messages.mark-replied", $contactMessage->id) }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function() { location.reload(); }
        });
    });

    $('#btnArchive').on('click', function() {
        $.ajax({
            url: '{{ route("contact-messages.archive", $contactMessage->id) }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function() { location.reload(); }
        });
    });
});
</script>
@endsection
