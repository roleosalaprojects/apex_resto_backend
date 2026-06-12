@extends('superadmin.layouts.master')

@section('title')
    Color Palettes
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item active">Color Palettes</li>
@endsection

@section('style')
    <style>
        .swatch-row { display: flex; gap: 6px; }
        .swatch { width: 24px; height: 24px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .badge-pill { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .badge-default { background: #ecfeff; color: #0e7490; }
        .badge-inactive { background: #fef2f2; color: #991b1b; }
        .palette-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    </style>
@endsection

@section('content')
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h2 style="margin:0;font-size:1.1rem;font-weight:600;">Color Palettes</h2>
                <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem;">Curated palettes tenants can choose from on their Branding page.</p>
            </div>
            <a href="{{ route('superadmin.color-palettes.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Palette
            </a>
        </div>
        <div class="card-body">
            {{-- Flash banners are rendered by superadmin/layouts/message.blade.php. --}}

            <table id="palettesTable" class="table" style="width:100%">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Label</th>
                        <th>Swatch</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th style="width:280px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            $('#palettesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('superadmin.color-palettes.data') }}",
                order: [[3, 'asc']],
                columns: [
                    { data: 'key' },
                    { data: 'label' },
                    {
                        data: 'swatch',
                        orderable: false,
                        searchable: false,
                        render: function (val) {
                            const colors = (val || '').split(',');
                            return '<div class="swatch-row">' + colors.map(function (c) {
                                return '<span class="swatch" style="background:' + c + '" title="' + c + '"></span>';
                            }).join('') + '</div>';
                        }
                    },
                    { data: 'sort_order' },
                    {
                        data: 'is_default',
                        orderable: false,
                        searchable: false,
                        render: function (val, type, row) {
                            const tags = [];
                            if (row.is_default) tags.push('<span class="badge-pill badge-default">Default</span>');
                            if (!row.is_active) tags.push('<span class="badge-pill badge-inactive">Inactive</span>');
                            return tags.length ? tags.join(' ') : '<span style="color:#94a3b8">Active</span>';
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function (id, type, row) {
                            const csrf = '{{ csrf_token() }}';
                            const editUrl = '{{ url('superadmin/color-palettes') }}/' + id + '/edit';
                            const setDefaultUrl = '{{ url('superadmin/color-palettes') }}/' + id + '/set-default';
                            const toggleUrl = '{{ url('superadmin/color-palettes') }}/' + id + '/toggle-active';
                            const deleteUrl = '{{ url('superadmin/color-palettes') }}/' + id;
                            let html = '<div class="palette-actions">';
                            html += '<a href="' + editUrl + '" class="btn btn-sm">Edit</a>';
                            if (!row.is_default && row.is_active) {
                                html += '<form method="POST" action="' + setDefaultUrl + '" style="display:inline"><input type="hidden" name="_token" value="' + csrf + '"><button class="btn btn-sm">Set default</button></form>';
                            }
                            if (!row.is_default) {
                                html += '<form method="POST" action="' + toggleUrl + '" style="display:inline"><input type="hidden" name="_token" value="' + csrf + '"><button class="btn btn-sm">' + (row.is_active ? 'Deactivate' : 'Activate') + '</button></form>';
                            }
                            if (!row.is_default) {
                                html += '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(\'Delete this palette?\')"><input type="hidden" name="_token" value="' + csrf + '"><input type="hidden" name="_method" value="DELETE"><button class="btn btn-sm btn-danger">Delete</button></form>';
                            }
                            html += '</div>';
                            return html;
                        }
                    }
                ]
            });
        });
    </script>
@endsection
