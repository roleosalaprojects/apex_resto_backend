@extends('layout.app')
@section('header')
    - Edit SMS Template
@endsection
@section('title')
    Edit SMS Template
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sms-templates.index') }}">SMS Templates</a></li>
    <li class="breadcrumb-item text-muted">{{ $template->key }}</li>
@endsection
@section('content')
    {{-- Flash banners are rendered by layout/messages.blade.php. --}}

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-5">
                <div class="card-body">
                    <form method="POST" action="{{ route('sms-templates.update', $template) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-5">
                            <label class="form-label fw-semibold">Key</label>
                            <input type="text" value="{{ $template->key }}" disabled class="form-control">
                            <small class="text-muted">Defined in code — not editable.</small>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" value="{{ $template->description }}" disabled class="form-control">
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-semibold required">Message Body</label>
                            <textarea name="body" rows="5" maxlength="480"
                                      class="form-control @error('body') is-invalid @enderror"
                                      id="bodyField">{{ old('body', $template->body) }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">
                                    Placeholders: <code>{brand}</code>, <code>{reference}</code>,
                                    <code>{customer_name}</code>, <code>{total}</code>. Unknown placeholders are left as-is.
                                </small>
                                <small class="text-muted" id="charCount">0 / 480</small>
                            </div>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-8">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input type="hidden" name="enabled" value="0">
                                <input type="checkbox" name="enabled" value="1"
                                       id="enabledToggle"
                                       class="form-check-input"
                                       {{ old('enabled', $template->enabled) ? 'checked' : '' }}>
                                <label for="enabledToggle" class="form-check-label fw-semibold ms-3">
                                    Enabled
                                    <small class="d-block text-muted fw-normal">Off = this event is muted. No SMS is sent for transitions matching this key.</small>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('sms-templates.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-outline ki-check fs-5"></i> Save Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Live preview</h4>
                    <p class="text-muted fs-7 mb-4">Rendered with sample placeholders so you can sanity-check before saving.</p>
                    <div class="border rounded p-4 mb-4" style="background: #f5f7fb;">
                        <div class="fs-7 text-muted mb-2">Sample data</div>
                        <ul class="fs-7 mb-3">
                            @foreach ($sample as $name => $value)
                                <li><code>{{ $name }}</code> = {{ $value }}</li>
                            @endforeach
                        </ul>
                        <div class="fs-7 text-muted mb-2">Output ({{ strlen($preview) }} chars)</div>
                        <div class="border rounded p-3 bg-white">{{ $preview }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const body = document.getElementById('bodyField');
            const counter = document.getElementById('charCount');
            const update = () => {
                const len = body.value.length;
                counter.textContent = `${len} / 480`;
                counter.style.color = len > 320 ? '#dc3545' : len > 160 ? '#f59e0b' : '#6c757d';
            };
            body.addEventListener('input', update);
            update();
        })();
    </script>
@endsection
