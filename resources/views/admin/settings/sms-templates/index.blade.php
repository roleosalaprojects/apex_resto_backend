@extends('layout.app')
@section('header')
    - SMS Templates
@endsection
@section('title')
    SMS Templates
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">SMS Templates</li>
@endsection
@section('content')
    {{-- Flash banners are rendered by layout/messages.blade.php. --}}

    <div class="card">
        <div class="card-body">
            <p class="text-muted fs-7 mb-5">
                These templates power the SMS messages sent to customers for order status changes. Toggle
                <code>Enabled</code> off to mute a specific event without uninstalling the feature. Placeholders
                like <code>{brand}</code>, <code>{reference}</code>, <code>{customer_name}</code>, and
                <code>{total}</code> are substituted at send time.
            </p>

            <table class="table table-row-bordered table-row-gray-200 align-middle gy-4 w-100">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th>Key</th>
                        <th>Description</th>
                        <th>Body (preview)</th>
                        <th>Enabled</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td><code>{{ $template->key }}</code></td>
                            <td>{{ $template->description }}</td>
                            <td class="text-muted fs-7" style="max-width: 420px;">{{ Str::limit($template->body, 140) }}</td>
                            <td>
                                @if ($template->enabled)
                                    <span class="badge badge-light-success">Enabled</span>
                                @else
                                    <span class="badge badge-light-secondary">Muted</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('sms-templates.edit', $template) }}" class="btn btn-sm btn-light-primary">
                                    <i class="ki-outline ki-pencil fs-5"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                No templates yet. Run <code>php artisan db:seed --class=SmsTemplateSeeder</code>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
