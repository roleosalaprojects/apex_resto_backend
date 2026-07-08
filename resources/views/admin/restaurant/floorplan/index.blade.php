@extends('layout.app')
@section('header')
    - Floorplan
@endsection
@section('title')
    Floorplan
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Floorplan</li>
@endsection
@section('actions')
    @if ($access->rstrnt_create)
        <a href="{{ route('restaurant-tables.create') }}" class="btn btn-primary">Add Table</a>
    @endif
    <a href="{{ route('restaurant-tables.index') }}" class="btn btn-light">Manage Tables</a>
@endsection
@section('content')
    <div class="row mb-5">
        <div class="col">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-6 py-4">
                    <span class="d-flex align-items-center gap-2">
                        <span class="bullet bullet-dot bg-success h-10px w-10px"></span>
                        <span class="text-muted">Available</span>
                        <span class="fw-bold" id="countAvailable">–</span>
                    </span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="bullet bullet-dot bg-danger h-10px w-10px"></span>
                        <span class="text-muted">Occupied</span>
                        <span class="fw-bold" id="countOccupied">–</span>
                    </span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="bullet bullet-dot bg-warning h-10px w-10px"></span>
                        <span class="text-muted">Reserved</span>
                        <span class="fw-bold" id="countReserved">–</span>
                    </span>
                    <span class="ms-auto text-muted fs-7" id="floorplanSync">Loading…</span>
                </div>
            </div>
        </div>
    </div>
    <div id="floorplanAreas"></div>
@endsection
@section('scripts')
    <script>
        (function () {
            const STATUS = {
                0: {label: 'Available', badge: 'badge-light-success', border: 'border-success'},
                1: {label: 'Occupied', badge: 'badge-light-danger', border: 'border-danger'},
                2: {label: 'Reserved', badge: 'badge-light-warning', border: 'border-warning'},
                3: {label: 'Inactive', badge: 'badge-light', border: ''},
            };
            const peso = (v) => '₱' + Number(v).toLocaleString('en-PH', {minimumFractionDigits: 2});

            function render(tables) {
                const areas = {};
                tables.forEach(t => (areas[t.area] = areas[t.area] || []).push(t));

                let counts = {0: 0, 1: 0, 2: 0};
                let html = '';
                Object.keys(areas).forEach(area => {
                    html += `<div class="mb-2"><h3 class="fw-bold">${area}
                        <span class="text-muted fs-7 fw-normal ms-2">${areas[area].length} tables</span></h3></div>`;
                    html += '<div class="row g-4 mb-8">';
                    areas[area].forEach(t => {
                        if (t.status in counts) counts[t.status]++;
                        const s = STATUS[t.status] || STATUS[3];
                        const open = t.open_order;
                        html += `
                        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                            <a href="${t.edit_url}" class="card border ${s.border} h-100 text-reset text-hover-primary">
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="badge ${s.badge}">${s.label}</span>
                                        <span class="text-muted fs-8">${t.seats ?? '–'} pax</span>
                                    </div>
                                    <div class="fs-2 fw-bolder">${t.name}</div>
                                    ${open
                                        ? `<div class="mt-auto pt-2">
                                             <div class="fw-bold text-danger">${peso(open.amount)}</div>
                                             <div class="text-muted fs-8">${open.reference}</div>
                                           </div>`
                                        : '<div class="mt-auto pt-2 text-muted fs-8">No open order</div>'}
                                </div>
                            </a>
                        </div>`;
                    });
                    html += '</div>';
                });

                document.getElementById('floorplanAreas').innerHTML =
                    html || '<div class="text-center text-muted py-20">No tables yet — add your first table to build the floor.</div>';
                document.getElementById('countAvailable').textContent = counts[0];
                document.getElementById('countOccupied').textContent = counts[1];
                document.getElementById('countReserved').textContent = counts[2];
                document.getElementById('floorplanSync').textContent =
                    'Updated ' + new Date().toLocaleTimeString();
            }

            function refresh() {
                fetch('{{ route('restaurant-tables.floorplan-data') }}', {headers: {'Accept': 'application/json'}})
                    .then(r => r.json())
                    .then(d => render(d.tables))
                    .catch(() => {
                        document.getElementById('floorplanSync').textContent = 'Connection lost — retrying…';
                    });
            }

            refresh();
            setInterval(refresh, 10000);
        })();
    </script>
@endsection
