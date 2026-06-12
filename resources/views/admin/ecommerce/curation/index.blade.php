@extends('layout.app')
@section('title')Shop Curation@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Shop Curation</li>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('styles')
    <style>
        .curation-shell { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 1199px) { .curation-shell { grid-template-columns: 1fr; } }

        /* Featured list (left) */
        .curation-list { list-style: none; padding: 0; margin: 0; min-height: 80px; }
        .curation-row {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px; margin-bottom: 8px;
            background: #fff; border: 1px solid #e4e6ef; border-radius: 8px;
            transition: box-shadow .15s ease;
        }
        .curation-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .curation-row.dragging { opacity: .5; }
        .curation-row.ghost { background: #f1f8ff; border-color: #a5d4ff; }
        .curation-handle {
            cursor: grab; color: #a1a5b7; user-select: none;
            font-size: 18px; padding: 2px 4px;
        }
        .curation-handle:active { cursor: grabbing; }
        .curation-icon { font-size: 20px; line-height: 1; }
        .curation-name { flex: 1; font-weight: 600; color: #181c32; }
        .curation-meta { color: #7e8299; font-size: 12px; }
        .curation-img {
            width: 36px; height: 36px; border-radius: 6px; object-fit: cover;
            background: #f1f3f7;
        }
        .curation-empty {
            padding: 24px; text-align: center; color: #a1a5b7;
            border: 2px dashed #e4e6ef; border-radius: 8px;
        }
        .curation-cap {
            background: #f3f6f9; color: #5e6278; padding: 8px 12px;
            border-radius: 6px; font-size: 13px; margin-top: 12px;
        }


        /* Toast */
        .curation-toast {
            position: fixed; bottom: 24px; right: 24px;
            background: #181c32; color: #fff; padding: 12px 18px;
            border-radius: 8px; opacity: 0; pointer-events: none;
            transition: opacity .25s ease; z-index: 9999;
        }
        .curation-toast.show { opacity: 1; }
    </style>
@endsection
@section('content')
    <div class="card card-flush mb-5">
        <div class="card-header pt-5">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-dark">Shop Curation</span>
                <span class="text-gray-500 mt-1 fw-semibold fs-7">
                    Pick the categories and products you want spotlighted on your /shop homepage. Drag to reorder. Up to {{ $maxDisplayed }} per surface are shown.
                </span>
            </h3>
        </div>
        <div class="card-body pt-2">
            <ul class="nav nav-tabs nav-line-tabs fs-6 mb-5" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab-categories">Featured Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-items">Featured Products</a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Categories tab --}}
                <div class="tab-pane fade show active" id="tab-categories">
                    <div class="curation-shell" data-curation-tab="categories">
                        <div>
                            <div class="fw-bold text-dark mb-2">Currently Featured (drag to reorder)</div>
                            <ul class="curation-list" data-curation-featured></ul>
                            <div class="curation-empty d-none" data-curation-empty>
                                Nothing featured yet. Use the table on the right to add categories.
                            </div>
                            <div class="curation-cap"><span data-curation-count>0</span> featured · cap shown on /shop: {{ $maxDisplayed }}</div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="fw-bold text-dark">Add Categories</div>
                                <input type="text" class="form-control w-200px" placeholder="Search Category" data-curation-search="categoriesCandidatesTable">
                            </div>
                            <div class="table-responsive">
                                <table id="categoriesCandidatesTable" class="table table-row-dashed table-row-gray-300 gs-4 gy-4" style="width:100%">
                                    <thead>
                                        <tr class="fw-semibold fs-6 text-muted">
                                            <th>Name</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Items tab --}}
                <div class="tab-pane fade" id="tab-items">
                    <div class="curation-shell" data-curation-tab="items">
                        <div>
                            <div class="fw-bold text-dark mb-2">Currently Featured (drag to reorder)</div>
                            <ul class="curation-list" data-curation-featured></ul>
                            <div class="curation-empty d-none" data-curation-empty>
                                Nothing featured yet. Use the table on the right to add products.
                            </div>
                            <div class="curation-cap"><span data-curation-count>0</span> featured · cap shown on /shop: {{ $maxDisplayed }}</div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="fw-bold text-dark">Add Products</div>
                                <input type="text" class="form-control w-200px" placeholder="Search Product" data-curation-search="itemsCandidatesTable">
                            </div>
                            <div class="table-responsive">
                                <table id="itemsCandidatesTable" class="table table-row-dashed table-row-gray-300 gs-4 gy-4" style="width:100%">
                                    <thead>
                                        <tr class="fw-semibold fs-6 text-muted">
                                            <th>Name</th>
                                            <th>Price</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="curation-toast" id="curationToast" aria-live="polite"></div>
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
@endsection
@section('scripts')
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const toast = document.getElementById('curationToast');
            const showToast = (msg) => {
                toast.textContent = msg;
                toast.classList.add('show');
                clearTimeout(toast._t);
                toast._t = setTimeout(() => toast.classList.remove('show'), 1800);
            };

            const TABS = {
                categories: {
                    label: 'category',
                    tableId: '#categoriesCandidatesTable',
                    urls: {
                        featured: '{{ route('shop.curation.categories.featured') }}',
                        search: '{{ route('shop.curation.categories.search') }}',
                        feature: (id) => `/admin/shop/curation/categories/${id}/feature`,
                        reorder: '{{ route('shop.curation.categories.reorder') }}',
                    },
                    renderIcon: (row) => `<span class="curation-icon">${row.icon || '🛒'}</span>`,
                    renderMeta: () => '',
                    columns: [
                        {
                            data: 'name',
                            render: (name, type, full) => `
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40px">
                                        <span class="symbol-label fs-2" style="background: #f4f6f9;">${full.icon || '🛒'}</span>
                                    </div>
                                    <div class="ms-4 fw-bold">${name}</div>
                                </div>
                            `,
                        },
                        {
                            data: 'id',
                            orderable: false,
                            searchable: false,
                            className: 'text-end',
                            render: (id) => `<button class="btn btn-sm btn-light-primary" data-curation-add="${id}">+ Add</button>`,
                        },
                    ],
                },
                items: {
                    label: 'product',
                    tableId: '#itemsCandidatesTable',
                    urls: {
                        featured: '{{ route('shop.curation.items.featured') }}',
                        search: '{{ route('shop.curation.items.search') }}',
                        feature: (id) => `/admin/shop/curation/items/${id}/feature`,
                        reorder: '{{ route('shop.curation.items.reorder') }}',
                    },
                    renderIcon: (row) => row.image
                        ? `<img class="curation-img" src="/${row.image}" alt="">`
                        : `<span class="curation-icon">📦</span>`,
                    renderMeta: (row) => row.price != null
                        ? `<span class="curation-meta">₱${Number(row.price).toFixed(2)}</span>`
                        : '',
                    columns: [
                        {
                            data: 'name',
                            render: (name, type, full) => {
                                const img = full.image ? full.image : 'assets/media/svg/general/rhone.svg';
                                return `
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label" style="background-image: url('/${img}');"></div>
                                        </div>
                                        <div class="ms-5 fw-bold">${name}</div>
                                    </div>
                                `;
                            },
                        },
                        {
                            data: 'price',
                            searchable: false,
                            render: (data, type, full) => {
                                // Match items index convention: green if price set;
                                // red + computed (cost + cost*markup/100) when zero.
                                let price = Number(full.price);
                                let label = 'text-success';
                                if (price === 0) {
                                    const cost = Number(full.cost) || 0;
                                    const markup = Number(full.markup) || 0;
                                    price = cost + (cost * (markup / 100));
                                    label = 'text-danger';
                                }
                                return `<span class="${label} fw-semibold">${price.toFixed(2)}</span>`;
                            },
                        },
                        {
                            data: 'id',
                            orderable: false,
                            searchable: false,
                            className: 'text-end',
                            render: (id) => `<button class="btn btn-sm btn-light-primary" data-curation-add="${id}">+ Add</button>`,
                        },
                    ],
                },
            };

            async function call(method, url, body) {
                const opts = {
                    method,
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                };
                if (body !== undefined) {
                    opts.headers['Content-Type'] = 'application/json';
                    opts.body = JSON.stringify(body);
                }
                const resp = await fetch(url, opts);
                if (!resp.ok) throw new Error(`${method} ${url} → ${resp.status}`);
                return resp.json();
            }

            function setupTab(tabKey) {
                const cfg = TABS[tabKey];
                const root = document.querySelector(`[data-curation-tab="${tabKey}"]`);
                if (!root) return;

                const featuredEl = root.querySelector('[data-curation-featured]');
                const emptyEl = root.querySelector('[data-curation-empty]');
                const countEl = root.querySelector('[data-curation-count]');

                async function loadFeatured() {
                    const { data } = await call('GET', cfg.urls.featured);
                    featuredEl.innerHTML = '';
                    data.forEach((row) => featuredEl.appendChild(featuredRow(row)));
                    countEl.textContent = data.length;
                    emptyEl.classList.toggle('d-none', data.length > 0);
                }

                function featuredRow(row) {
                    const li = document.createElement('li');
                    li.className = 'curation-row';
                    li.dataset.id = row.id;
                    li.innerHTML = `
                        <span class="curation-handle">≡</span>
                        ${cfg.renderIcon(row)}
                        <span class="curation-name">${escapeHtml(row.name)}</span>
                        ${cfg.renderMeta(row)}
                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" title="Unfeature">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    `;
                    li.querySelector('button').addEventListener('click', async () => {
                        try {
                            await call('DELETE', cfg.urls.feature(row.id));
                            showToast(`Removed from featured ${cfg.label}.`);
                            await loadFeatured();
                            $(cfg.tableId).DataTable().ajax.reload(null, false);
                        } catch (e) { showToast('Could not unfeature. Try again.'); }
                    });
                    return li;
                }

                async function persistOrder() {
                    const ids = Array.from(featuredEl.children).map((li) => Number(li.dataset.id));
                    if (ids.length === 0) return;
                    try {
                        await call('POST', cfg.urls.reorder, { ids });
                        showToast('Order saved.');
                    } catch (e) { showToast('Could not save order.'); await loadFeatured(); }
                }

                Sortable.create(featuredEl, {
                    animation: 150,
                    handle: '.curation-handle',
                    ghostClass: 'ghost',
                    dragClass: 'dragging',
                    onEnd: persistOrder,
                });

                // Length selector + info at bottom-left, pagination at bottom-right,
                // matching the items index convention. No 'f' since the custom search
                // input above the table is the only search box. autoWidth: false
                // skips the layout-measurement step that would otherwise produce zero
                // column widths if this table was initialized on a hidden tab.
                const dt = $(cfg.tableId).DataTable({
                    serverSide: true,
                    processing: true,
                    responsive: true,
                    autoWidth: false,
                    pageLength: 10,
                    order: [[0, 'asc']],
                    ajax: { url: cfg.urls.search, type: 'GET' },
                    columns: cfg.columns,
                    dom: "rt<'row mt-3'<'col-sm-6 d-flex align-items-center'li><'col-sm-6 d-flex justify-content-end'p>>",
                    language: {
                        emptyTable: `No ${cfg.label}s available.`,
                        zeroRecords: `No matching ${cfg.label}s. Try a different keyword.`,
                    },
                });

                // Bind the prominent search input to the DataTable's search.
                // Debounced so we don't fire on every keystroke.
                const searchInput = document.querySelector(`[data-curation-search="${cfg.tableId.slice(1)}"]`);
                if (searchInput) {
                    let timer;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => dt.search(e.target.value).draw(), 220);
                    });
                }

                // Add button click delegate
                $(cfg.tableId).on('click', '[data-curation-add]', async function () {
                    const id = $(this).data('curation-add');
                    try {
                        await call('POST', cfg.urls.feature(id));
                        showToast(`Added to featured ${cfg.label}s.`);
                        await loadFeatured();
                        dt.ajax.reload(null, false);
                    } catch (e) { showToast('Could not feature. Try again.'); }
                });

                loadFeatured();
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, (c) => (
                    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
                ));
            }

            $(document).ready(() => {
                setupTab('categories');
                setupTab('items');

                // Re-adjust column widths every time a tab becomes visible so a
                // table that was initialized while hidden lays out cleanly the
                // first time the user sees it.
                $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
                });
            });
        })();
    </script>
@endsection
