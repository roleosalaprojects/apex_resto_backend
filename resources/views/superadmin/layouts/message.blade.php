@if (session()->has('msg'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>{{ session()->get('msg') }}</div>
    </div>
@endif

@if (session()->has('success'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>{{ session()->get('success') }}</div>
    </div>
@endif

@if (session()->has('error'))
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>{{ session()->get('error') }}</div>
    </div>
@endif

@if (session()->has('danger-callout'))
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>{{ session()->get('danger-callout') }}</div>
    </div>
@endif

@if (session()->has('warning'))
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>{{ session()->get('warning') }}</div>
    </div>
@endif
