@section('head')
    <meta name="_token" content="{{ csrf_token() }}">
@endsection
@section('style')
    <link rel="stylesheets" href='{{ asset("plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css")}}'>
    <link rel="stylesheets" href='{{ asset("plugins/toastr/toastr.min.css")}}'>
    <link rel="stylesheets" href='{{ asset("plugins/icheck-bootstrap/icheck-bootstrap.min.css")}}'>
@endsection
<div class="d-flex flex-column flex-lg-row fv-plugins-bootstrap5 fv-plugins-framework" id="fields">
    <!--begin::Aside column-->
    <div class="d-flex flex-column gap-7 gap-lg-10 w-100 w-lg-300px mb-7 me-lg-10">
        <!--begin::Category & tags-->
        <div class="card card-flush py-4">
            <!--begin::Card header-->
            <div class="card-header">
                <!--begin::Card title-->
                <div class="card-title">
                    <h2>Product Details</h2>
                </div>
                <!--end::Card title-->
            </div>
            <!--end::Card header-->
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Image input placeholder-->
                <style>
                    .image-input-placeholder {
                        background-image: url('{{asset("/assets/media/svg/shapes/abstract-4.svg")}}');
                    }

                    [data-bs-theme="dark"] .image-input-placeholder {
                        background-image: url('{{asset("/assets/media/svg/shapes/abstract-4-dark.svg")}}');
                    }
                </style>
                <!--end::Image input placeholder-->

                <!--begin::Image input-->
                <div class="image-input image-input-outline mb-10" data-kt-image-input="true"
                     style="background-image: url({{asset("/assets/media/svg/shapes/abstract-4.svg")}}">
                    <!--begin::Image preview wrapper-->
                    <div class="image-input-wrapper w-150px h-150px"
                         style="background-image: url({{$item->image ? asset($item->image) : asset("/assets/media/svg/shapes/abstract-4-dark.svg")}})"></div>
                    <!--end::Image preview wrapper-->

                    <!--begin::Edit button-->
                    <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                           data-kt-image-input-action="change"
                           data-bs-toggle="tooltip"
                           data-bs-dismiss="click"
                           title="Change avatar">
                        <i class="fa-solid fa-pencil"></i>

                        <!--begin::Inputs-->
                        <input type="file" name="image" accept=".png, .jpg, .jpeg"/>
                        <input type="hidden" name="old_image" value="{{$item->image}}"/>
                        <!--end::Inputs-->
                    </label>
                    <!--end::Edit button-->

                    <!--begin::Cancel button-->
                    <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                          data-kt-image-input-action="cancel"
                          data-bs-toggle="tooltip"
                          data-bs-dismiss="click"
                          title="Cancel avatar">
                        <i class="fa-solid fa-trash"></i>
                    </span>
                    <!--end::Cancel button-->

                    <!--begin::Remove button-->
                    <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                          data-kt-image-input-action="remove"
                          data-bs-toggle="tooltip"
                          data-bs-dismiss="click"
                          title="Remove avatar">
                        <i class="fa-solid fa-trash"></i>
                    </span>
                    <!--end::Remove button-->
                </div>
                <!--end::Image input-->


                <!--begin::Input group-->
                <div class="form-group">
                    <!--begin::Label-->
                    <label class="form-label">Categories</label>
                    <!--end::Label-->
                    <!--begin::Select2-->
                    <select class="form-select" data-control="select2" data-placeholder="Select an option"
                            data-allow-clear="true" id="categorySelect" name="category">
                        <option></option>
                    </select>
                    <!--end::Select2-->
                    <!--begin::Description-->
                    <div class="text-muted fs-7 mb-3">Add product to a category.</div>
                    <!--end::Description-->
                    <!--end::Input group-->
                </div>
                <div class="form-group mb-5">
                    <label class="form-label" for="supplier">Supplier</label>
                    <select name="supplier" id="supplierSelect" class="form-select" data-control="select2"
                            data-placeholder="Select an option" data-allow-clear="true">
                        <option value=""></option>
                    </select>
                    <!--begin::Description-->
                    <div class="text-muted fs-7 mb-3">Add product to a supplier.</div>
                    <!--end::Description-->
                </div>
                {{-- Product Type --}}
                <div class="form-group mb-5">
                    <label for="" class="form-label required">Product Type</label>
                    <div class="d-flex">
                        <div class="custom-control custom-radio me-10">
                            <input type="radio" name="type" id="radio1" class="custom-control-input"
                                   value="1" {{ ($item->type == true) ? 'checked' : ''}}>
                            <label for="radio1" class="custom-control-label form-label">Sold By Weight</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" name="type" id="radio2" class="custom-control-input"
                                   value="0" {{ ($item->type == false) ? 'checked' : '' }}>
                            <label for="radio2" class="custom-control-label form-label">Sold By Piece</label>
                        </div>
                    </div>
                </div>
                {{-- Tax --}}
                <div class="form-group mb-5 fv-row">
                    <label for="vatable" class="form-label">Tax Class</label>
                    <select class="form-select" value="{{ $item->vatable }}" name="vatable" id="vatable" data-hide-search="true">
                        <option value="0">VAT Exempted</option>
                        <option value="1">VAT Included</option>
                        <option value="2">Zero Rated</option>
                    </select>
                    @error('vatable') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="form-group mb-5 fv-row">
                    <label for="rate" class="form-label required">TAX Type</label>
                    <select name="rate" id="taxSelect" class="form-select" data-control="select2"
                            data-placeholder="Select an option" data-allow-clear="true">
                        <option value=""></option>
                    </select>
                    <!--begin::Description-->
                    <div class="text-muted fs-7 mb-3">Add product to a TAX classification.</div>
                    <!--end::Description-->
                </div>
                <div class="form-check mb-5">
                    <input type="checkbox" class="form-check-input" name="creditable_to_points" id="creditable_to_points" {{ Route::currentRouteName() == 'items.edit' ? $item->creditable_to_points ? 'checked' : '' : '' }}>
                    <label for="creditable_to_points" class="form-check-label required">Creditable for points?</label>
                </div>
            </div>

            <!--end::Card body-->
        </div>
        <!--end::Category & tags-->
    </div>
    <!--end::Aside column-->
    <!--begin::Main column-->
    <div class="d-flex flex-column flex-row-fluid gap-7 gap-lg-10">
        <ul class="nav nav-custom nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-4 fw-semibold mb-n2">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#general_tab">General</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#advance_tab">Advanced</a>
            </li>
        </ul>
        <div class="tab-content" id="tabContent">
            {{-- General Tab --}}
            <div class="tab-pane fade show active" id="general_tab" role="tabpanel">
                <div class="d-flex flex-column gap-7 gap-lg-10">
                    <!--begin::General options-->
                    <div class="card card-flush py-4">
                        <!--begin::Card header-->
                        <div class="card-header">
                            <div class="card-title">
                                <h2>General</h2>
                            </div>
                        </div>
                        <!--end::Card header-->
                        <!--begin::Card body-->
                        <div class="card-body pt-0">
                            <div class="mb-5 fv-row">
                                <label for="name" class="form-label required">Product Name"</label>
                                <input type="text" class="form-control required" name="name" id="name" value="{{$item->name ? $item->name : old('name')}}">
                                <!--begin::Description-->
                                <div class="text-muted fs-7">A product name is required and recommended to be unique.
                                </div>
                                <!--end::Description-->
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="mb-5 fv-row">
                                <label for="barcode" class="form-label required">Product Barcode</label>
                                <input type="text" class="form-control" name="barcode" id="barcode" value="{{$item->barcode ? $item->barcode : old('barcode')}}">
                                <!--begin::Description-->
                                <div class="text-muted fs-7">A product barcode is recommended to be unique.</div>
                                <!--end::Description-->
                                @error('barcode') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="mb-5 fv-row">
                                <label for="cost" class="form-label required">Product Cost</label>
                                <input type="number" class="form-control" step=".01" min="0" name="cost" id="cost" value="{{$item->cost ? $item->cost : old('cost')}}" data-numeric-only>
                                @error('cost') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <!--end::Card header-->
                    </div>
                    <!--end::General options-->
                    {{-- begin::Pricing options --}}
                    <div class="card card-flush py-4">
                        <!--begin::Card header-->
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Pricing</h2>
                            </div>
                        </div>
                        <!--end::Card header-->
                        <!--begin::Card body-->
                        <div class="card-body pt-0">
                            <div class="mb-5 fv-row">
                                <label for="main_price" class="form-label required">Base Price</label>
                                <input type="number" class="form-control" name="main_price" id="main_price" value="{{$item->price ? $item->price : old('main_price')}}" data-numeric-only>
                                <div class="text-muted fs-7 mt-1">
                                    Margin vs cost: <span id="basePriceMargin" class="fw-semibold">—</span>
                                </div>
                                @error('main_price') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="mb-5 fv-row">
                                <label for="markup" class="form-label required">Markup (%)</label>
                                <input type="number" class="form-control mb-2" name="markup" id="markup" value="{{$item->markup ? $item->markup : old('markup')}}">
                                <!--begin::Description-->
                                <div class="text-muted fs-7">Product markup will be automatically calculated if Base
                                    Price is set to zero(0).
                                </div>
                                <!--end::Description-->
                                @error('markup') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            {{-- begin:Special Discounts --}}
                            <div class="mb-5 fv-row">
                                <!--begin::Option-->
                                <input type="radio" class="btn-check" name="discountable" value="0" checked="checked"
                                       id="no_discount"/>
                                <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center mb-5"
                                       for="no_discount">
                                    <span class="d-block fw-semibold text-start">
                                        <span class="text-dark fw-bold d-block fs-5">No Discount</span>
                                    </span>
                                </label>
                                <!--end::Option-->

                                <!--begin::Option-->
                                <input type="radio" class="btn-check" name="discountable" value="1"
                                       id="special_discount"/>
                                <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center mb-5"
                                       for="special_discount">
                                    <span class="d-block fw-semibold text-start">
                                        <span class="text-dark fw-bold d-block fs-5">Special Discount</span>
                                        <span class="text-muted fw-semibold fs-6">Allow Special Discount for Senior Citizens, Person/s with Disablities (PWD), Solo Parent, National Athletes and Coaches</span>
                                    </span>
                                    @error('discountable') <span class="text-danger">{{ $message }}</span> @enderror
                                </label>
                                <!--end::Option-->
                                <div class="d-none" id="specialDiscounts">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3 fv-row">
                                                <label for="senior" class="fomr-label required">Senior Citizen Discount</label>
                                                <input type="number" class="form-control" name="senior" id="senior" value="{{ ($item->senior) ? $item->senior : 20 }}" min="0">
                                                @error('senior') <span class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="mb-3 fv-row">
                                                <label for="pwd" class="fomr-label required">PWD Discount</label>
                                                <input type="number" class="form-control" name="pwd" id="pwd" value="{{ ($item->pwd) ? $item->pwd : 20 }}" min="0">
                                                @error('pwd') <span class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 fv-row">
                                                <label for="solo_parent" class="fomr-label required">Solo Parent</label>
                                                <input type="number" class="form-control" name="solo_parent" id="solo_parent" value="{{ ($item->solo_parent) ? $item->solo_parent : 10 }}" min="0">
                                                @error('solo_parent') <span class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="mb-3 fv-row">
                                                <label for="naac" class="fomr-label required">Senior Citizen Discount</label>
                                                <input type="number" class="form-control" name="naac" id="naac" value="{{ ($item->naac) ? $item->naac : 20 }}" min="0">
                                                @error('naac') <span
                                                        class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="separator border-5 my-5"></div>
                                </div>
                            </div>
                        </div>
                        <!--end::Card header-->
                    </div>
                    {{-- end::Pricing options --}}
                </div>
            </div>
            {{-- Advanced Tab --}}
            <div class="tab-pane fade" id="advance_tab" role="tabpanel">
                {{-- begin::UoM --}}
                <div class="card card-flush py-4 mb-10">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Unit of Measures</h2>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        @php
                            $canManageUnitLock = (bool) (auth()->user()->role->unit_lock ?? false);
                        @endphp
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Unit</th>
                                <th>QTY</th>
                                <th>Price</th>
                                <th>Cost basis</th>
                                <th>Margin</th>
                                <th>Barcode</th>
                                @if($canManageUnitLock)
                                    <th>Lock</th>
                                @endif
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="unitTable">
                            @if($item->itemUnits)
                                @foreach($item->itemUnits as $product_unit)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="uom_id[]" value="{{ $product_unit->unit_id }}"/>
                                            {{ ($product_unit->unit) ? $product_unit->unit->name : "N/A" }}
                                        </td>
                                        <td>
                                            <input type="number" name="qty[]" class="form-control"
                                                   value="{{$product_unit->qty}}" data-numeric-only required>
                                        </td>
                                        <td>
                                            <input type="number" name="price[]" class="form-control"
                                                   value="{{$product_unit->price}}" min="0" data-numeric-only required>
                                        </td>
                                        <td class="align-middle">
                                            <span class="js-cost-basis text-muted">—</span>
                                        </td>
                                        <td class="align-middle">
                                            <span class="js-margin fw-semibold">—</span>
                                        </td>
                                        <td>
                                            <input type="text" name="uom_barcode[]" class="form-control"
                                                   value="{{$product_unit->barcode}}"/>
                                        </td>
                                        @if($canManageUnitLock)
                                            <td class="align-middle text-center">
                                                <div class="form-check form-switch form-check-custom form-check-solid">
                                                    {{-- Single hidden value per row (kept in sync by JS) so locked[] aligns with uom_id[]. --}}
                                                    <input type="hidden" name="locked[]" value="{{ $product_unit->locked ? 1 : 0 }}" data-locked-flag>
                                                    <input class="form-check-input" type="checkbox" data-locked-toggle {{ $product_unit->locked ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                        @endif
                                        <td>
                                            <button type="button" class="btn btn-danger btn-icon">
                                                    <span class="svg-icon svg-icon-muted svg-icon-2hx">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                             viewBox="0 0 24 24" fill="none">
                                                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2"
                                                                  rx="1" transform="rotate(-45 6 17.3137)"
                                                                  fill="black"/>
                                                            <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                  transform="rotate(45 7.41422 6)" fill="black"/>
                                                        </svg>
                                                    </span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="{{ $canManageUnitLock ? 7 : 6 }}">
                                    <select name="units" id="unitSelect" class="form-select" data-control="select2"
                                            data-placeholder="Select Unit">
                                        <option value=""></option>
                                    </select>
                                </th>
                                <th>
                                    <button type="button" id="btnAddUnit" class="btn btn-light-info">Add</button>
                                </th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                {{-- end::Uom --}}
                {{-- begin::Recipe (composite item) --}}
                <div class="card card-flush py-4 mb-10">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Recipe / Components</h2>
                        </div>
                        <div class="card-toolbar">
                            <span class="text-muted fs-7">Composite items deduct component stock when sold.</span>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label for="uom_label" class="form-label">Stock Unit Label</label>
                                <input type="text" class="form-control" name="uom_label" id="uom_label"
                                       maxlength="10" placeholder="g / ml / pc"
                                       value="{{ old('uom_label', $item->uom_label) }}">
                                <div class="text-muted fs-7">Fine unit this item is stocked in (for ingredients).</div>
                            </div>
                            <div class="col-md-8 d-flex align-items-center">
                                <div class="form-check mt-5">
                                    <input type="checkbox" class="form-check-input" name="cost_override" id="cost_override"
                                            {{ old('cost_override', $item->cost_override) ? 'checked' : '' }}>
                                    <label for="cost_override" class="form-check-label">
                                        Pin cost manually (skip auto-recalculation from components)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Component</th>
                                <th class="w-150px">Qty</th>
                                <th class="w-125px">Unit Cost</th>
                                <th class="w-125px">Line Cost</th>
                                <th>Notes</th>
                                <th class="w-50px"></th>
                            </tr>
                            </thead>
                            <tbody id="componentTable">
                            @if($item->exists)
                                @foreach($item->components()->with('componentItem')->get() as $idx => $recipe_line)
                                    <tr data-cost="{{ $recipe_line->componentItem->cost ?? 0 }}">
                                        <td>
                                            <input type="hidden" name="components[{{ $idx }}][component_item_id]"
                                                   value="{{ $recipe_line->component_item_id }}"/>
                                            {{ $recipe_line->componentItem->name ?? 'N/A' }}
                                            <span class="text-muted">({{ $recipe_line->componentItem->uom_label ?? 'pc' }})</span>
                                        </td>
                                        <td>
                                            <input type="number" name="components[{{ $idx }}][qty]" class="form-control"
                                                   value="{{ $recipe_line->qty + 0 }}" min="0.0001" step="any" required>
                                        </td>
                                        <td class="align-middle text-end js-component-unit-cost">
                                            {{ number_format($recipe_line->componentItem->cost ?? 0, 2) }}
                                        </td>
                                        <td class="align-middle text-end js-component-line-cost">—</td>
                                        <td>
                                            <input type="text" name="components[{{ $idx }}][notes]" class="form-control"
                                                   value="{{ $recipe_line->notes }}" maxlength="255">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-icon btn-sm js-remove-component">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="2">
                                    <select id="componentSelect" class="form-select" data-placeholder="Search item or barcode">
                                        <option value=""></option>
                                    </select>
                                </th>
                                <th>
                                    <button type="button" id="btnAddComponent" class="btn btn-light-info">Add</button>
                                </th>
                                <th colspan="3" class="text-end align-middle">
                                    Computed cost: <span id="recipeTotalCost" class="fw-bold">0.00</span>
                                </th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                {{-- end::Recipe --}}
                {{-- begin::Inventory --}}
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Inventory</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @forelse ($locations as $store)
                            <div class="mb-3 form-group">
                                <input type="hidden" name="store[]" value="{{ $store->id }}">
                                <label for="stock"></label>
                                <label for="stock" class="form-label required"></label>
                                <input type="number" class="form-control required" name="stock" id="stock" value="{{ ($store->stocks) ? $store->stocks->stock : 0 }}">
                                @error('store')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        @empty
                            <tr>
                                <td colspan="2">No Branch Locations Found. Please Add At Least 1 to Proceed.</td>
                            </tr>
                        @endforelse
                    </div>
                </div>
                {{-- end::Inventory --}}
            </div>
        </div>
        <div class="d-flex justify-content-end">
            <!--begin::Button-->
            <a href="{{ url()->previous() }}" id="kt_ecommerce_add_product_cancel" class="btn btn-light me-5">Cancel</a>
            <!--end::Button-->
            <!--begin::Button-->
            <button type="button" id="btnSubmit" class="btn btn-primary">
                <span class="indicator-label">Save Changes</span>
                <span class="indicator-progress">Please wait...
                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
            <!--end::Button-->
        </div>
    </div>
    <!--end::Main column-->
</div>

<script>
    // Real-time cost-basis + margin calculations, numeric-only enforcement,
    // and a save-time confirm if any UoM (or the base price) sells below cost.
    // Lives in the shared _fields partial so it runs on both create and edit.
    (function () {
        const canManageUnitLock = @json($canManageUnitLock ?? false);

        const costEl = document.getElementById('cost');
        const mainPriceEl = document.getElementById('main_price');
        const basePriceMarginEl = document.getElementById('basePriceMargin');
        const unitTable = document.getElementById('unitTable');

        const toNum = (v) => {
            const n = parseFloat(v);
            return Number.isFinite(n) ? n : NaN;
        };
        const fmtMoney = (n) => Number.isFinite(n) ? n.toFixed(2) : '—';
        const fmtPct = (n) => Number.isFinite(n) ? `${n >= 0 ? '+' : ''}${n.toFixed(1)}%` : '—';

        function setMargin(el, margin) {
            if (!el) {
                return;
            }
            el.textContent = fmtPct(margin);
            el.classList.toggle('text-danger', Number.isFinite(margin) && margin < 0);
            el.classList.toggle('text-success', Number.isFinite(margin) && margin >= 0);
        }

        function recalcBasePrice() {
            const cost = toNum(costEl?.value);
            const price = toNum(mainPriceEl?.value);
            if (!Number.isFinite(cost) || cost <= 0) {
                setMargin(basePriceMarginEl, NaN);
                return;
            }
            setMargin(basePriceMarginEl, ((price - cost) / cost) * 100);
        }

        function recalcRow(tr) {
            if (!tr || !costEl) {
                return;
            }
            const qtyInput = tr.querySelector('input[name="qty[]"]');
            const priceInput = tr.querySelector('input[name="price[]"]');
            if (!qtyInput || !priceInput) {
                return;
            }

            const cost = toNum(costEl.value);
            const qty = toNum(qtyInput.value);
            const price = toNum(priceInput.value);
            const basis = cost * qty;

            const basisEl = tr.querySelector('.js-cost-basis');
            if (basisEl) {
                basisEl.textContent = fmtMoney(basis);
            }

            if (!Number.isFinite(basis) || basis <= 0) {
                setMargin(tr.querySelector('.js-margin'), NaN);
                return;
            }
            setMargin(tr.querySelector('.js-margin'), ((price - basis) / basis) * 100);
        }

        function recalcAll() {
            recalcBasePrice();
            unitTable?.querySelectorAll('tr').forEach(recalcRow);
        }

        // --- Wire up real-time recalc.
        costEl?.addEventListener('input', recalcAll);
        mainPriceEl?.addEventListener('input', recalcBasePrice);
        unitTable?.addEventListener('input', (e) => {
            const tr = e.target.closest('tr');
            if (tr && (e.target.matches('input[name="qty[]"]') || e.target.matches('input[name="price[]"]'))) {
                recalcRow(tr);
            }
        });

        // --- Numeric-only enforcement on data-numeric-only inputs (and qty[]/price[]).
        function isNumericText(text) {
            return /^-?\d*\.?\d*$/.test(text);
        }
        function attachNumericGuard(input) {
            input.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                if (!isNumericText(text)) {
                    e.preventDefault();
                }
            });
            input.addEventListener('keypress', (e) => {
                // Allow control keys; block any non-digit/dot/minus characters.
                if (e.key.length === 1 && !/[\d.\-]/.test(e.key)) {
                    e.preventDefault();
                }
            });
        }
        document.querySelectorAll('input[data-numeric-only]').forEach(attachNumericGuard);
        // Apply to dynamic rows too (event delegation can't filter "first time", so we do it
        // post-add via a MutationObserver scoped to the table body).
        if (unitTable) {
            new MutationObserver((mutations) => {
                mutations.forEach((m) => {
                    m.addedNodes.forEach((node) => {
                        if (node.nodeType !== 1) {
                            return;
                        }
                        node.querySelectorAll('input[name="qty[]"], input[name="price[]"]').forEach(attachNumericGuard);
                        recalcRow(node.closest('tr') ?? node);
                    });
                });
            }).observe(unitTable, { childList: true });
        }

        // --- Keep the per-row hidden locked flag in sync with its toggle checkbox.
        function syncLockedFlag(checkbox) {
            const hidden = checkbox.closest('td')?.querySelector('input[data-locked-flag]');
            if (hidden) {
                hidden.value = checkbox.checked ? '1' : '0';
            }
        }
        unitTable?.addEventListener('change', (e) => {
            if (e.target.matches('input[data-locked-toggle]')) {
                syncLockedFlag(e.target);
            }
        });

        // --- Save-time guard: if any margin is negative, confirm before submit.
        const form = document.getElementById('itemForm') ?? document.querySelector('form');
        form?.addEventListener('submit', (e) => {
            const negatives = [];
            if (basePriceMarginEl?.classList.contains('text-danger')) {
                negatives.push('Base Price');
            }
            unitTable?.querySelectorAll('tr').forEach((tr) => {
                const m = tr.querySelector('.js-margin');
                if (m?.classList.contains('text-danger')) {
                    const cell = tr.querySelector('td');
                    const name = cell ? cell.textContent.trim() : 'Unit';
                    negatives.push(name);
                }
            });
            if (negatives.length > 0) {
                const ok = window.confirm(
                    `Warning: the following will sell BELOW cost:\n\n  • ${negatives.join('\n  • ')}\n\nProceed anyway?`
                );
                if (!ok) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            }
        }, true);

        // --- Initial pass.
        recalcAll();

        // Expose for create/edit page scripts that build dynamic rows.
        window.__itemUomRecalc = { recalcRow, recalcAll, attachNumericGuard, canManageUnitLock };
    })();
</script>
<script>
    // Recipe / components repeater. Runs after window load so jQuery +
    // select2 (initialised by the page-level scripts) are guaranteed present.
    window.addEventListener('load', function () {
        if (!window.jQuery) {
            return;
        }
        var $ = window.jQuery;
        var componentTable = $('#componentTable');
        var componentSelect = $('#componentSelect');
        var totalCostEl = document.getElementById('recipeTotalCost');
        var nextIndex = componentTable.find('tr').length;

        componentSelect.select2({
            ajax: {
                url: '{{ route('items.select') }}',
                delay: 250,
                type: 'get',
                dataType: 'json',
                data: function (params) {
                    return { term: params.term };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true
            }
        });

        function recalcRecipe() {
            var total = 0;
            componentTable.find('tr').each(function () {
                var cost = parseFloat($(this).data('cost')) || 0;
                var qty = parseFloat($(this).find('input[name$="[qty]"]').val()) || 0;
                var line = cost * qty;
                total += line;
                $(this).find('.js-component-line-cost').text(line.toFixed(2));
            });
            if (totalCostEl) {
                totalCostEl.textContent = total.toFixed(2);
            }
        }

        $('#btnAddComponent').on('click', function () {
            var itemId = componentSelect.val();
            if (!itemId) {
                return;
            }
            if (componentTable.find('input[name$="[component_item_id]"][value="' + itemId + '"]').length) {
                componentSelect.val(null).trigger('change');
                return;
            }
            $.get('/admin/items/get/' + itemId, function (component) {
                var label = component.uom_label || 'pc';
                var row = $('<tr data-cost="' + (component.cost || 0) + '">' +
                    '<td><input type="hidden" name="components[' + nextIndex + '][component_item_id]" value="' + component.id + '"/>' +
                    $('<span>').text(component.name).html() + ' <span class="text-muted">(' + label + ')</span></td>' +
                    '<td><input type="number" name="components[' + nextIndex + '][qty]" class="form-control" value="1" min="0.0001" step="any" required></td>' +
                    '<td class="align-middle text-end js-component-unit-cost">' + parseFloat(component.cost || 0).toFixed(2) + '</td>' +
                    '<td class="align-middle text-end js-component-line-cost">—</td>' +
                    '<td><input type="text" name="components[' + nextIndex + '][notes]" class="form-control" maxlength="255"></td>' +
                    '<td><button type="button" class="btn btn-danger btn-icon btn-sm js-remove-component"><i class="fas fa-times"></i></button></td>' +
                    '</tr>');
                componentTable.append(row);
                nextIndex++;
                recalcRecipe();
            });
            componentSelect.val(null).trigger('change');
        });

        componentTable.on('click', '.js-remove-component', function () {
            $(this).closest('tr').remove();
            recalcRecipe();
        });

        componentTable.on('input', 'input[name$="[qty]"]', recalcRecipe);

        recalcRecipe();
    });
</script>
