<!--begin::Navbar-->
<div class="app-navbar flex-shrink-0 gap-2 gap-lg-4">
    @yield('actions')
    @php
        $canApproveAccess = auth()->user()->role
            && (auth()->user()->role->discounts
                || auth()->user()->role->rfnd
                || auth()->user()->role->delete_items
                || auth()->user()->role->csh_out
                || auth()->user()->role->crdt_sale
                || auth()->user()->role->unit_lock_approve);
        $canSeeNewOrders = (bool) auth()->user()->role?->sls;
    @endphp

    @if ($canSeeNewOrders)
        <!--begin::New ecommerce orders bell-->
        <div class="app-navbar-item" id="newOrdersBell">
            <div class="position-relative cursor-pointer btn btn-icon btn-active-light-primary w-40px h-40px"
                 data-kt-menu-trigger="click"
                 data-kt-menu-attach="parent"
                 data-kt-menu-placement="bottom-end"
                 title="New ecommerce orders">
                <i class="ki-outline ki-handcart fs-1"></i>
                <span id="newOrdersBellBadge"
                      class="badge badge-circle badge-danger w-10px h-10px position-absolute top-0 end-0 mt-2 me-2 d-none"></span>
            </div>
            <!--begin::Orders dropdown-->
            <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-400px"
                 data-kt-menu="true">
                <div class="d-flex flex-column bgi-no-repeat rounded-top px-9 py-7"
                     style="background-color: var(--bs-primary);">
                    <h3 class="text-white fw-bold m-0">
                        New Orders
                        <span id="newOrdersHeaderCount" class="fs-7 opacity-75 ms-2"></span>
                    </h3>
                </div>
                <div id="newOrdersList" class="scroll-y" style="max-height: 420px;">
                    <div id="newOrdersEmpty" class="text-center text-muted px-9 py-10">
                        <i class="ki-outline ki-check-circle fs-2x text-success mb-3 d-block"></i>
                        <div class="fs-6">No pending orders.</div>
                        <div class="fs-7">New ones appear here automatically.</div>
                    </div>
                </div>
                <div class="px-9 py-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted fs-7" id="newOrdersUpdatedAt">Polling every {{ (int) config('notifications.order_feed_poll_ms') / 1000 }}s…</span>
                    <a href="{{ route('ecommerce-orders.index') }}" class="btn btn-sm btn-light-primary">View all</a>
                </div>
            </div>
            <!--end::Orders dropdown-->
        </div>
        <!--end::New ecommerce orders bell-->
        <script>
            (function () {
                const POLL_INTERVAL_MS = @json((int) config('notifications.order_feed_poll_ms'));
                const feedUrl = @json(route('ecommerce-orders.pending-feed'));

                const badge = document.getElementById('newOrdersBellBadge');
                const list = document.getElementById('newOrdersList');
                const emptyState = document.getElementById('newOrdersEmpty');
                const headerCount = document.getElementById('newOrdersHeaderCount');
                const updatedAt = document.getElementById('newOrdersUpdatedAt');

                const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));

                const peso = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                function rowHtml(o) {
                    return `
                        <a href="${escapeHtml(o.url)}" class="d-block text-reset text-decoration-none border-bottom">
                            <div class="px-7 py-4 hover-bg-light-primary">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="fw-bold fs-6">${escapeHtml(o.reference)}</span>
                                    <span class="badge badge-light-warning ms-2">Pending</span>
                                    <span class="text-muted fs-8 ms-auto">${escapeHtml(o.created_at)}</span>
                                </div>
                                <div class="text-muted fs-7">${escapeHtml(o.customer_name)} · ${o.qty} item${o.qty === 1 ? '' : 's'}</div>
                                <div class="fw-semibold fs-6 mt-1">${peso(o.total)}</div>
                            </div>
                        </a>
                    `;
                }

                function render(body) {
                    const count = Number(body?.count || 0);
                    const orders = Array.isArray(body?.orders) ? body.orders : [];
                    if (count === 0) {
                        list.innerHTML = '';
                        list.appendChild(emptyState);
                        badge.classList.add('d-none');
                        headerCount.textContent = '';
                        return;
                    }
                    emptyState.remove();
                    list.innerHTML = orders.map(rowHtml).join('');
                    badge.classList.remove('d-none');
                    headerCount.textContent = `(${count} pending)`;
                }

                let inflight = false;
                async function poll() {
                    if (inflight) return;
                    inflight = true;
                    try {
                        const res = await fetch(feedUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (!res.ok) return;
                        const body = await res.json();
                        render(body);
                        const now = new Date();
                        updatedAt.textContent = `Updated ${now.toLocaleTimeString()}`;
                    } catch (_) {
                        // Network blip; try again next tick.
                    } finally {
                        inflight = false;
                    }
                }

                poll();
                setInterval(poll, POLL_INTERVAL_MS);
            })();
        </script>
    @endif
    @if ($canApproveAccess)
        <!--begin::Access-request notifications-->
        <div class="app-navbar-item" id="accessRequestsBell">
            <div class="position-relative cursor-pointer btn btn-icon btn-active-light-primary w-40px h-40px"
                 data-kt-menu-trigger="click"
                 data-kt-menu-attach="parent"
                 data-kt-menu-placement="bottom-end">
                <i class="ki-outline ki-notification-status fs-1"></i>
                {{-- Metronic dot indicator. Count lives inside the dropdown header. --}}
                <span id="accessRequestsBellBadge"
                      class="badge badge-circle badge-danger w-10px h-10px position-absolute top-0 end-0 mt-2 me-2 d-none"></span>
            </div>
            <!--begin::Notifications dropdown-->
            <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-400px"
                 data-kt-menu="true">
                <!--begin::Header-->
                <div class="d-flex flex-column bgi-no-repeat rounded-top px-9 py-7"
                     style="background-color: var(--bs-primary);">
                    <h3 class="text-white fw-bold m-0">
                        Access Requests
                        <span id="accessRequestsHeaderCount" class="fs-7 opacity-75 ms-2"></span>
                    </h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div id="accessRequestsList" class="scroll-y" style="max-height: 420px;">
                    <div id="accessRequestsEmpty" class="text-center text-muted px-9 py-10">
                        <i class="ki-outline ki-check-circle fs-2x text-success mb-3 d-block"></i>
                        <div class="fs-6">No pending requests.</div>
                        <div class="fs-7">New ones appear here automatically.</div>
                    </div>
                </div>
                <!--end::Body-->
                <!--begin::Footer-->
                <div class="px-9 py-3 border-top">
                    <span class="text-muted fs-7" id="accessRequestsUpdatedAt">Polling every {{ (int) config('notifications.access_request_poll_ms') / 1000 }}s…</span>
                </div>
                <!--end::Footer-->
            </div>
            <!--end::Notifications dropdown-->
        </div>
        <!--end::Access-request notifications-->
        <script>
            (function () {
                const POLL_INTERVAL_MS = @json((int) config('notifications.access_request_poll_ms'));
                const pendingUrl = @json(route('access-requests.pending'));
                const respondUrlBase = @json(url('admin/access-requests'));
                const csrfToken = @json(csrf_token());

                const bell = document.getElementById('accessRequestsBell');
                const badge = document.getElementById('accessRequestsBellBadge');
                const list = document.getElementById('accessRequestsList');
                const emptyState = document.getElementById('accessRequestsEmpty');
                const headerCount = document.getElementById('accessRequestsHeaderCount');
                const updatedAt = document.getElementById('accessRequestsUpdatedAt');

                const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));

                function renderContext(type, ctx) {
                    if (!ctx) {
                        return '';
                    }
                    if (type === 'locked_unit') {
                        return `${escapeHtml(ctx.item_name ?? '')} — <span class="fw-semibold">${escapeHtml(ctx.unit_name ?? '')}</span>`;
                    }
                    return Object.entries(ctx)
                        .map(([k, v]) => `${escapeHtml(k)}: ${escapeHtml(typeof v === 'object' ? JSON.stringify(v) : v)}`)
                        .join(', ');
                }

                function rowHtml(req) {
                    const ctx = renderContext(req.permission_type, req.context_data);
                    return `
                        <div class="d-flex flex-column px-7 py-5 border-bottom" data-request-id="${escapeHtml(req.request_id)}">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-light-primary me-2">${escapeHtml(req.permission_label)}</span>
                                <span class="text-muted fs-7 ms-auto">${req.remaining_seconds}s</span>
                            </div>
                            <div class="fw-semibold fs-6 mb-1">${escapeHtml(req.user_name)}</div>
                            <div class="text-muted fs-7 mb-1">${escapeHtml(req.store_name)} • ${escapeHtml(req.pos_name)}</div>
                            ${ctx ? `<div class="fs-7 mb-3">${ctx}</div>` : ''}
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light-success flex-grow-1" data-action="approved">Approve</button>
                                <button type="button" class="btn btn-sm btn-light-danger flex-grow-1" data-action="denied">Deny</button>
                            </div>
                        </div>
                    `;
                }

                function render(rows) {
                    if (!rows.length) {
                        list.innerHTML = '';
                        list.appendChild(emptyState);
                        badge.classList.add('d-none');
                        headerCount.textContent = '';
                        return;
                    }
                    emptyState.remove();
                    list.innerHTML = rows.map(rowHtml).join('');
                    badge.classList.remove('d-none');
                    headerCount.textContent = `(${rows.length} pending)`;
                }

                let inflight = false;
                async function poll() {
                    if (inflight) {
                        return;
                    }
                    inflight = true;
                    try {
                        const res = await fetch(pendingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (!res.ok) {
                            return;
                        }
                        const body = await res.json();
                        render(body?.data?.requests ?? []);
                        const now = new Date();
                        updatedAt.textContent = `Updated ${now.toLocaleTimeString()}`;
                    } catch (_) {
                        // Network blip; try again next tick.
                    } finally {
                        inflight = false;
                    }
                }

                async function respond(requestId, status) {
                    try {
                        const res = await fetch(`${respondUrlBase}/${encodeURIComponent(requestId)}/respond`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ status }),
                        });
                        if (!res.ok) {
                            const err = await res.json().catch(() => ({}));
                            alert(err.message ?? 'Could not respond — please retry.');
                            return;
                        }
                        // Optimistic removal; next poll reconciles.
                        const row = list.querySelector(`[data-request-id="${requestId}"]`);
                        row?.remove();
                        const remaining = list.querySelectorAll('[data-request-id]').length;
                        if (remaining === 0) {
                            render([]);
                        } else {
                            headerCount.textContent = `(${remaining} pending)`;
                        }
                    } catch (_) {
                        alert('Network error — please retry.');
                    }
                }

                list.addEventListener('click', (e) => {
                    const btn = e.target.closest('button[data-action]');
                    if (!btn) {
                        return;
                    }
                    const row = btn.closest('[data-request-id]');
                    const requestId = row?.dataset.requestId;
                    if (requestId) {
                        respond(requestId, btn.dataset.action);
                    }
                });

                poll();
                setInterval(poll, POLL_INTERVAL_MS);
            })();
        </script>
    @endif
    <!--begin::My apps links-->
    <!--begin::User menu-->
    <div class="app-navbar-item" id="kt_header_user_menu_toggle">
        <!--begin::Menu wrapper-->
        <div class="cursor-pointer symbol symbol-40px"
             data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
             data-kt-menu-attach="parent"
             data-kt-menu-placement="bottom-end">
            <img src="{{ (auth()->user()->details?->image) ? asset(auth()->user()->details->image) : asset("assets/media/avatars/blank.png") }}" class="rounded-3" alt="user"/>
        </div>
        <!--layout-partial:partials/menus/_user-account-menu.html-->
        @include('layout.partials.menus._user-account-menu')
        <!--end::Menu wrapper-->
    </div>
    <!--end::User menu-->
</div>
<!--end::Navbar-->
