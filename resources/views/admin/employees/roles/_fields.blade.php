<div class="d-flex flex-column flex-lg-row">
    <!--begin::Sidebar-->
    <div class="d-flex flex-column gap-7 gap-lg-10 w-100 w-lg-300px mb-7 me-lg-10">
        <!--begin::Role name-->
        <div class="card card-flush py-4">
            <div class="card-header">
                <div class="card-title">
                    <h2>Role Details</h2>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="form-group">
                    <label for="name" class="form-label required">Name</label>
                    <input type="text" name="name" id="name" value="{{ $role->name }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
                    <span class="text-danger">{{ $errors->has('name') ? 'Cannot be blank!' : '' }}</span>
                </div>
            </div>
        </div>
        <!--end::Role name-->
        <!--begin::Nav pills-->
        <div class="card card-flush py-4">
            <div class="card-body py-0">
                <ul class="nav nav-pills nav-pills-custom flex-column" id="role_tabs" role="tablist">
                    <li class="nav-item mb-3" role="presentation">
                        <a class="nav-link active d-flex align-items-center px-4 py-3" data-bs-toggle="pill" href="#tab_pos" role="tab">
                            <i class="ki-outline ki-handcart fs-3 me-3"></i>
                            <span class="fs-6 fw-semibold">POS &amp; General</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3" role="presentation">
                        <a class="nav-link d-flex align-items-center px-4 py-3" data-bs-toggle="pill" href="#tab_customers" role="tab">
                            <i class="ki-outline ki-people fs-3 me-3"></i>
                            <span class="fs-6 fw-semibold">Sales &amp; Customers</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3" role="presentation">
                        <a class="nav-link d-flex align-items-center px-4 py-3" data-bs-toggle="pill" href="#tab_inventory" role="tab">
                            <i class="ki-outline ki-parcel fs-3 me-3"></i>
                            <span class="fs-6 fw-semibold">Inventory</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3" role="presentation">
                        <a class="nav-link d-flex align-items-center px-4 py-3" data-bs-toggle="pill" href="#tab_employees" role="tab">
                            <i class="ki-outline ki-profile-user fs-3 me-3"></i>
                            <span class="fs-6 fw-semibold">Employees</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3" role="presentation">
                        <a class="nav-link d-flex align-items-center px-4 py-3" data-bs-toggle="pill" href="#tab_accounting" role="tab">
                            <i class="ki-outline ki-wallet fs-3 me-3"></i>
                            <span class="fs-6 fw-semibold">Accounting</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link d-flex align-items-center px-4 py-3" data-bs-toggle="pill" href="#tab_settings" role="tab">
                            <i class="ki-outline ki-setting-2 fs-3 me-3"></i>
                            <span class="fs-6 fw-semibold">Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <!--end::Nav pills-->
    </div>
    <!--end::Sidebar-->

    <!--begin::Content-->
    <div class="d-flex flex-column flex-lg-row-fluid gap-7 gap-lg-10">
        <div class="tab-content" id="role_tab_content">

            {{-- ========== POS & General ========== --}}
            <div class="tab-pane fade show active" id="tab_pos" role="tabpanel">
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>POS &amp; General Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- POS --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="pos" name="pos" {{ $role->pos ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="pos">POS</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Make a sale</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Delete Items --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="delete_items" name="delete_items" {{ $role->delete_items ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="delete_items">Remove Items from Ticket</label>
                            </div>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Refunds --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="rfnd" name="rfnd" {{ $role->rfnd ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="rfnd">Make Refunds</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Perform a refund</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Discounts --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="discounts" name="discounts" {{ $role->discounts ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="discounts">Apply Discounts w/ Restrictions</label>
                            </div>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Cash Out --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="csh_out" name="csh_out" {{ $role->csh_out ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="csh_out">Void Cash Out</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Void cash-out entries</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Credit Sale --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="crdt_sale" name="crdt_sale" {{ $role->crdt_sale ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="crdt_sale">Approve Credit Sale</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Approve a cashier's runtime request to ring up a credit sale</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Credit Payment --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="crdt_pymnt" name="crdt_pymnt" {{ $role->crdt_pymnt ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="crdt_pymnt">Accept Credit Payment</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Accept a payment against a customer's outstanding credit</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Unit Lock (Web) --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="unit_lock" name="unit_lock" {{ $role->unit_lock ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="unit_lock">Lock / Unlock Units</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Mark a UoM as locked on the item form</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Unit Lock Approve (Runtime) --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="unit_lock_approve" name="unit_lock_approve" {{ $role->unit_lock_approve ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="unit_lock_approve">Approve Locked Unit Override</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Approve a cashier's runtime request to use a locked UoM</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Print --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="print" name="print" {{ $role->print ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="print">Reprint Receipts</label>
                            </div>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- Back Office --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="bck_offc" name="bck_offc" {{ $role->bck_offc ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="bck_offc">Back Office</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Access the Back Office</span>
                        </div>
                        <div class="separator my-3"></div>
                        {{-- View Sales --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="sls" name="sls" {{ $role->sls ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="sls">View Sales</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== Sales & Customers ========== --}}
            <div class="tab-pane fade" id="tab_customers" role="tabpanel">
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Sales &amp; Customer Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- Customer Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="cstmr" name="cstmr" data-toggle-children="cstmr" {{ $role->cstmr ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="cstmr">Customer Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="cstmr">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="cstmr_read" name="cstmr_read" {{ $role->cstmr_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cstmr_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="cstmr_create" name="cstmr_create" {{ $role->cstmr_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cstmr_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="cstmr_update" name="cstmr_update" {{ $role->cstmr_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cstmr_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="cstmr_delete" name="cstmr_delete" {{ $role->cstmr_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cstmr_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== Inventory ========== --}}
            <div class="tab-pane fade" id="tab_inventory" role="tabpanel">
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Inventory Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- Item Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="itms" name="itms" data-toggle-children="itms" {{ $role->itms ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="itms">Item Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="itms">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="itms_read" name="itms_read" {{ $role->itms_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="itms_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="itms_create" name="itms_create" {{ $role->itms_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="itms_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="itms_update" name="itms_update" {{ $role->itms_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="itms_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="itms_delete" name="itms_delete" {{ $role->itms_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="itms_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Inventory Counts --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="invntry" name="invntry" data-toggle-children="invntry" {{ $role->invntry ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="invntry">Inventory Counts</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="invntry">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="invntry_read" name="invntry_read" {{ $role->invntry_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="invntry_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="invntry_create" name="invntry_create" {{ $role->invntry_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="invntry_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="invntry_update" name="invntry_update" {{ $role->invntry_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="invntry_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="invntry_delete" name="invntry_delete" {{ $role->invntry_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="invntry_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Purchase Orders --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="prchs" name="prchs" data-toggle-children="prchs" {{ $role->prchs ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="prchs">Purchase Orders</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="prchs">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="prchs_read" name="prchs_read" {{ $role->prchs_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="prchs_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="prchs_create" name="prchs_create" {{ $role->prchs_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="prchs_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="prchs_update" name="prchs_update" {{ $role->prchs_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="prchs_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="prchs_delete" name="prchs_delete" {{ $role->prchs_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="prchs_delete">Delete</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" id="prchs_approve" name="prchs_approve" {{ $role->prchs_approve ? 'checked' : '' }}>
                                        <label class="form-check-label" for="prchs_approve">Approve</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Stock Adjustments --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="adjstmnts" name="adjstmnts" data-toggle-children="adjstmnts" {{ $role->adjstmnts ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="adjstmnts">Stock Adjustments</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="adjstmnts">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="adjstmnts_read" name="adjstmnts_read" {{ $role->adjstmnts_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="adjstmnts_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="adjstmnts_create" name="adjstmnts_create" {{ $role->adjstmnts_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="adjstmnts_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="adjstmnts_update" name="adjstmnts_update" {{ $role->adjstmnts_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="adjstmnts_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="adjstmnts_delete" name="adjstmnts_delete" {{ $role->adjstmnts_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="adjstmnts_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Transfer Orders --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="trnsfrs" name="trnsfrs" data-toggle-children="trnsfrs" {{ $role->trnsfrs ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="trnsfrs">Transfer Orders</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="trnsfrs">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="trnsfrs_read" name="trnsfrs_read" {{ $role->trnsfrs_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="trnsfrs_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="trnsfrs_create" name="trnsfrs_create" {{ $role->trnsfrs_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="trnsfrs_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="trnsfrs_update" name="trnsfrs_update" {{ $role->trnsfrs_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="trnsfrs_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="trnsfrs_delete" name="trnsfrs_delete" {{ $role->trnsfrs_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="trnsfrs_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Suppliers --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="spplrs" name="spplrs" data-toggle-children="spplrs" {{ $role->spplrs ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="spplrs">Suppliers</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="spplrs">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="spplrs_read" name="spplrs_read" {{ $role->spplrs_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="spplrs_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="spplrs_create" name="spplrs_create" {{ $role->spplrs_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="spplrs_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="spplrs_update" name="spplrs_update" {{ $role->spplrs_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="spplrs_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="spplrs_delete" name="spplrs_delete" {{ $role->spplrs_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="spplrs_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== Employees ========== --}}
            <div class="tab-pane fade" id="tab_employees" role="tabpanel">
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Employee Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- Employee Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="emplys" name="emplys" data-toggle-children="emplys" {{ $role->emplys ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="emplys">Employee Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="emplys">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="emplys_read" name="emplys_read" {{ $role->emplys_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="emplys_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="emplys_create" name="emplys_create" {{ $role->emplys_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="emplys_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="emplys_update" name="emplys_update" {{ $role->emplys_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="emplys_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="emplys_delete" name="emplys_delete" {{ $role->emplys_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="emplys_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Attendance Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="attndnc" name="attndnc" data-toggle-children="attndnc" {{ $role->attndnc ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="attndnc">Attendance Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="attndnc">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="attndnc_read" name="attndnc_read" {{ $role->attndnc_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="attndnc_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="attndnc_create" name="attndnc_create" {{ $role->attndnc_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="attndnc_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="attndnc_update" name="attndnc_update" {{ $role->attndnc_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="attndnc_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="attndnc_delete" name="attndnc_delete" {{ $role->attndnc_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="attndnc_delete">Delete</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" id="attndnc_schedules" name="attndnc_schedules" {{ $role->attndnc_schedules ? 'checked' : '' }}>
                                        <label class="form-check-label" for="attndnc_schedules">Schedules</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Role Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="rl" name="rl" data-toggle-children="rl" {{ $role->rl ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="rl">Role Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="rl">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="rl_read" name="rl_read" {{ $role->rl_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="rl_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="rl_create" name="rl_create" {{ $role->rl_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="rl_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="rl_update" name="rl_update" {{ $role->rl_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="rl_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="rl_delete" name="rl_delete" {{ $role->rl_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="rl_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== Accounting ========== --}}
            <div class="tab-pane fade" id="tab_accounting" role="tabpanel">
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Accounting Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- Banking --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="bnkng" name="bnkng" data-toggle-children="bnkng" {{ $role->bnkng ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="bnkng">Banking</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="bnkng">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="bnkng_read" name="bnkng_read" {{ $role->bnkng_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="bnkng_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="bnkng_create" name="bnkng_create" {{ $role->bnkng_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="bnkng_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="bnkng_update" name="bnkng_update" {{ $role->bnkng_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="bnkng_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="bnkng_delete" name="bnkng_delete" {{ $role->bnkng_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="bnkng_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Expenses --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="expnss" name="expnss" data-toggle-children="expnss" {{ $role->expnss ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="expnss">Expenses</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="expnss">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="expnss_read" name="expnss_read" {{ $role->expnss_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="expnss_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="expnss_create" name="expnss_create" {{ $role->expnss_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="expnss_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="expnss_update" name="expnss_update" {{ $role->expnss_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="expnss_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="expnss_delete" name="expnss_delete" {{ $role->expnss_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="expnss_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== Settings ========== --}}
            <div class="tab-pane fade" id="tab_settings" role="tabpanel">
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Settings Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- Store Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="str" name="str" data-toggle-children="str" {{ $role->str ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="str">Store Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="str">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="str_read" name="str_read" {{ $role->str_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="str_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="str_create" name="str_create" {{ $role->str_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="str_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="str_update" name="str_update" {{ $role->str_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="str_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="str_delete" name="str_delete" {{ $role->str_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="str_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- Tax Management --}}
                        <div class="border rounded p-5">
                            <div class="d-flex flex-wrap align-items-center gap-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="tax" name="tax" data-toggle-children="tax" {{ $role->tax ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold fs-5" for="tax">Tax Management</label>
                                </div>
                                <div class="vr d-none d-sm-block"></div>
                                <div class="d-flex flex-wrap align-items-center gap-5" data-parent="tax">
                                    <div class="form-check form-check-custom form-check-success">
                                        <input class="form-check-input" type="checkbox" id="tax_read" name="tax_read" {{ $role->tax_read ? 'checked' : '' }}>
                                        <label class="form-check-label" for="tax_read">Read</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-info">
                                        <input class="form-check-input" type="checkbox" id="tax_create" name="tax_create" {{ $role->tax_create ? 'checked' : '' }}>
                                        <label class="form-check-label" for="tax_create">Create</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-warning">
                                        <input class="form-check-input" type="checkbox" id="tax_update" name="tax_update" {{ $role->tax_update ? 'checked' : '' }}>
                                        <label class="form-check-label" for="tax_update">Update</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-danger">
                                        <input class="form-check-input" type="checkbox" id="tax_delete" name="tax_delete" {{ $role->tax_delete ? 'checked' : '' }}>
                                        <label class="form-check-label" for="tax_delete">Delete</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>

                        {{-- General Settings --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="sttngs" name="sttngs" {{ $role->sttngs ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="sttngs">General Settings</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Access and edit general settings</span>
                        </div>
                        <div class="separator my-3"></div>

                        {{-- Pulse Dashboard --}}
                        <div class="d-flex align-items-center py-4">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="pulse" name="pulse" {{ $role->pulse ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="pulse">Pulse Dashboard</label>
                            </div>
                            <span class="text-muted fs-7 ms-3">&mdash; Application monitoring dashboard</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!--end::Content-->
</div>

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function toggleChildren(parentKey, isChecked) {
            var container = document.querySelector('[data-parent="' + parentKey + '"]');
            if (!container) return;

            var inputs = container.querySelectorAll('input[type="checkbox"]');
            inputs.forEach(function (input) {
                input.disabled = !isChecked;
                if (!isChecked) {
                    input.checked = false;
                }
            });
        }

        document.querySelectorAll('[data-toggle-children]').forEach(function (parentCheckbox) {
            var key = parentCheckbox.getAttribute('data-toggle-children');

            toggleChildren(key, parentCheckbox.checked);

            parentCheckbox.addEventListener('change', function () {
                toggleChildren(key, this.checked);
            });
        });
    });
</script>
@endsection
