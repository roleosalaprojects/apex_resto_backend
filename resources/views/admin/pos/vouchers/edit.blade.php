@extends('layout.app')
@section('header')
    - Edit Voucher
@endsection
@section('title')
    Edit Voucher
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('vouchers.index') }}">Vouchers</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Edit</li>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="card-title">
                <i class="ki-duotone ki-ticket text-primary fs-1 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Edit Voucher: {{ $voucher->code }}
            </h3>
        </div>
        <form action="{{ route('vouchers.update', $voucher) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('admin.pos.vouchers._form')
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('vouchers.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Update Voucher
                </button>
            </div>
        </form>
    </div>
@endsection
@section('scripts')
<script>
$(function() {
    // Generate code button
    $('#generateCode').click(function() {
        $.get('{{ route("vouchers.generate-code") }}', function(response) {
            $('input[name="code"]').val(response.code);
        });
    });

    // Initialize Flatpickr for expiration date
    $("#expires_at").flatpickr({
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: false,
        altInput: true,
        altFormat: "F j, Y h:i K",
        defaultDate: "{{ old('expires_at', $voucher->expires_at ? $voucher->expires_at->format('Y-m-d H:i') : '') }}"
    });
});
</script>
@endsection
