<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div id="details" class="mb-10">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-5 fv-row">
                                {!! Form::label("supplier", "Supplier", ["class"=>"form-label required"]) !!}
                                <select id="supplierSelect" name="supplier" class="form-select {{ ($errors->has('store') ?? 'is-invalid') }}" data-control="select2" data-allow-clear="true" data-placeholder="Choose a Supplier">
                                    <option></option>
                                </select>
                                <span class="text-danger">{{$errors->has('supplier') ? "Supplier field cannot be empty!" : ''}}</span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-5 fv-row">
                                {!! Form::label("store", "Store", ["class"=>"form-label required"]) !!}
                                <select id="storeSelect" name="store" class="form-select {{ ($errors->has('store') ?? 'is-invalid') }}" data-control="select2" data-allow-clear="true" data-placeholder="Choose a Store">
                                    <option></option>
                                </select>
                                <span class="text-danger" id="storeMessage">{{$errors->has('store') ? "Store field cannot be empty!" : ''}}</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-5 fv-row">
                                {!! Form::label("purchased", "Purchased On", ["class"=>"form-label required"]) !!}
                                <input type="text" name="purchased" id="purchaseDate" class="form-control datetimepicker-input {{$errors->has('purchased') ? 'is-invalid' : ''}}" value="{{($purchase->purchased) ? $purchase->purchased : ''}}" data-toggle="datetimepicker" autocomplete="off" placeholder="Select Date">
                                <span class="text-danger">{{$errors->has('purchased') ? "Purchase Date field cannot be empty!" : ''}}</span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-5 fv-row">
                                {!! Form::label("expect", "Terms", ["class"=>"form-label required"]) !!}
                                <input type="number" name="expect" id="expectedDate" class="form-control datetimepicker-input {{$errors->has('expect') ? 'is-invalid' : ''}} " value="{{$purchase->expected ? $purchase->expected : 0}}" autocomplete="off" placeholder="No. of days to be paid">
                                <span class="text-danger">{{$errors->has('expect') ? "Expected Date field cannot be empty!" : ''}}</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group mb-5 fv-row">
                                {!! Form::label("invoice_no", "Invoice No.", ["class"=>"form-label required"]) !!}
                                {!! Form::text("invoice_no", $purchase->invoice_no, ["class"=>"form-control ".($errors->has('invoice_no') ? 'is-invalid' : ''), ]) !!}
                                @error('invoice_no')
                                    <span class="text-danger">{{$message}}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-5 fv-row">
                        {!! Form::label("note", "Note", ["class"=>"form-label"]) !!}
                        {!! Form::text("note", $purchase->note, ["class"=>"form-control"]) !!}
                    </div>
                </div>
                <div class="separator separator-content my-10">Products</div>
                <div id="itemsHolder">
                    <table class="table table-row-bordered gs-7 gy-7 gx-7" id="tableItems">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Stocks</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            @if (Route::current()->getName() == 'purchases.edit')
                                @foreach ($purchase->lines as $line)
                                    <tr>
                                        <td><input type="hidden" value="{{$line->item_id}}" name='item_id[]'/>{{$line->item->name}}</td>
                                        <td>
                                            <select name="unit[]" class="form-select" autocomplete="off">
                                                <option value="">PCS</option>
                                                @foreach ($line->item->itemUnits as $item_unit)
                                                    <option value="{{ $item_unit->unit_id }}" {{ $item_unit->unit_id == $line->unit_id ? 'selected' : '' }}>{{ $item_unit->unit->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            @foreach ($line->item->itemStores as $item_tore)
                                                {{ $item_tore->store->name }}: {{ $item_tore->stock }} <br />
                                            @endforeach
                                        </td>
                                        <td><input type="number" name="qty[]" step="any" class="form-control" value="{{$line->qty}}"></td>
                                        <td><input type="number" name="price[]" step="any" class="form-control" value="{{$line->cost}}"></td>
                                        <td><button id="DeleteButton" type="button" class="btn btn-danger btn-icon btn-flat btn-delete"><i class="fas fa-trash"></i>
                                        </button></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    <div class="my-10 separator"></div>
                    <div class="form-group mb-5 fv-row">
                        <select name="itemSelect" id="itemSelect" class="form-select" data-control="select2" data-placeholder="Select and Item / Product">
                            <option></option>
                            {{-- Search Items from here --}}
                        </select>
                    </div>
                </div>
                <div class="separator separator-content my-15">Other Costs Incurred</div>
                <div id="othersHolder">
                    <table class="table" id="tableItems">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Description</th>
                                <th>Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="addBody">
                            @if (Route::current()->getName() == 'purchases.edit')
                                @foreach ($purchase->adds as $add)
                                    <tr>
                                        <td>
                                            <input name="addDescription[]" type="text" class="form-control" value = "{{$add->description}}" required/></td>
                                        <td>
                                            <input name="addAmount[]" type="number" class="form-control" step="any" value = "{{$add->amount}}" required/>
                                        </td>
                                        <td>
                                            <button id="DeleteAdd" type="button" class="btn btn-danger btn-icon btn-flat"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                        <tfoot>
                            <th colspan="3">
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-warning" id="addRowAdditional">Add</button>
                                </div>
                            </th>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="error-list">

</div>
@section('vendor-styles')
    
@endsection
@section('vendor-scripts')
    
@endsection
@section('scripts')
    <script src="{{ asset('assets/js/swal.js') }}"></script>
    <script>
        $(document).ready(function(){
            let supplierSelect, storeSelect, purchaseDate, expectedDate, itemSelect, btnAddAddtional;
            init();

            function init(){
                supplierSelect = $("#supplierSelect");
                storeSelect = $("#storeSelect");
                itemSelect = $("#itemSelect");
                purchaseDate = $("#purchaseDate");
                expectedDate = $("#expectedDate");
                btnAddAddtional = $("#addRowAdditional");
                
                purchaseDate.daterangepicker({
                    singleDatePicker: true,
                    showDropdowns: true,
                    minYear: 2010,
                    {!! Route::current()->getName() == "purchases.edit" ? "startDate: moment(" . "'" . \Carbon\Carbon::parse($purchase->purchased)->toDateString() . "')" : '' !!}
                });

                // Select2 Elements
                supplierSelect.select2({
                    ajax: {
                        url: "{{ route('suppliers.select') }}",
                        type: "GET",
                        delay: 250,
                        dataType: "JSON",
                        data: function (params) {
                            var queryParameters = {
                                search: params.term
                            }
                            return queryParameters;
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                    }
                });
                storeSelect.select2({
                    ajax: {
                        url: "{{ route('stores.select') }}",
                        type: "GET",
                        delay: 250,
                        dataType: "JSON",
                        data: function (params) {
                            var queryParameters = {
                                search: params.term
                            }
                            return queryParameters;
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                    }
                });
                itemSelect.select2({
                    ajax: {
                        url: "{{ route('items.select') }}",
                        type: "GET",
                        delay: 250,
                        dataType: "JSON",
                        data: function (params) {
                            var queryParameters = {
                                term: params.term
                            }
                            return queryParameters;
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                    }
                });

                @if (Route::current()->getName() == 'purchases.edit')
                    // Pre-Select Select2 Values
                    // Store Select
                    $.ajax({
                        type:"get",
                        url: '{{ route("store.get", $purchase->store_id) }}'
                    }).then(function (response){
                        var data = response;
                        var option = new Option(data.name, data.id, true, true);
                        var select = storeSelect;
                        select.append(option).trigger('change');
                        select.trigger({
                            type: 'select2:select',
                            params: {
                                data: data
                            }
                        });
                    })
                    // Supplier Select
                    $.ajax({
                        type:"get",
                        url: '{{ route("supplier.get", $purchase->supplier_id) }}'
                    }).then(function (response){
                        var data = response;
                        var option = new Option(data.name, data.id, true, true);
                        var select = supplierSelect;
                        select.append(option).trigger('change');
                        select.trigger({
                            type: 'select2:select',
                            params: {
                                data: data
                            }
                        });
                    })
                @endif

                // Event Handlers
                itemSelect.on('select2:select', function(e){
                    var data = e.params.data;
                    (data) ? getItem(data) : "";
                    itemSelect.val(null).trigger('change');
                });

                storeSelect.on('select2:select', function(e){
                    (e.params.data) ? itemSelect.removeAttr('disabled') : itemSelect.attr('disabled');
                });
                // Remove Row from Table
                $('#itemsBody').on('click', '.btn-delete', function(e){
                    $(this).closest('tr').remove();
                })
                // Remove Row from Table
                $('#addBody').on('click', '.btn-delete-add', function(){
                    $(this).closest('tr').remove();
                })

                btnAddAddtional.on('click', function(e){
                    rowAddAdditional();
                });

                // functions
                function getItem(data){
                    $.ajax({
                        type: "GET",
                        data: {
                            id: data.id
                        },
                        url: "{{ url('admin/items/get') }}/" + data.id,
                        success: function(response){
                            rowAddItem(response);
                        },
                        error: function(response){
                            errorSwal('Something went wrong!', response)
                        }
                    })
                }

                function rowAddItem(data){
                    // Units
                    var unitOptions = `<option value="">PCS</option>`;
                    (data.item_units) ? data.item_units.map(function(e){
                        unitOptions += `<option value='${e.unit.id}'>${e.unit.name}</option>`
                    }) : '';
                    // Stores / Locations
                    let stocks = '';
                    data.item_stores.map(function(e){
                        stocks += e.store.name + ': ' + e.stock + '<br />';
                    });

                    let row = $('<tr>').append(
                        $('<td>').append(
                            $('<input>').attr({
                                type: 'hidden',
                                value: data.id,
                                name: 'item_id[]',
                            })
                        ).append(data.name)
                    ).append(
                        $('<td>').append(
                            $('<select>').attr({
                                name: 'unit[]',
                                class: 'form-select',
                                'data-control': 'select2',
                            }).append(unitOptions)
                        )
                    ).append(
                        $('<td>').append(
                            $('<span>').attr('id', 'stock_id').html(stocks)
                        )
                    ).append(
                        $('<td>').append(
                            $('<input>').attr({
                                type: 'number',
                                name: 'qty[]',
                                step: 'any',
                                class: 'form-control',
                            })
                        )
                    ).append(
                        $('<td>').append(
                            $('<input>').attr({
                                type: 'number',
                                name: 'price[]',
                                step: 'any',
                                class: 'form-control',
                                value: data.cost
                            })
                        )
                    ).append(
                        $('<td>').append(`<button id="DeleteButton" type="button" class="btn btn-danger btn-icon btn-delete"><i class="fas fa-trash"></i>
                            </button>`)
                    );

                    $('#itemsBody').append(row);
                }

                function rowAddAdditional(){
                    var row;
                    row = `<tr>
                        <td>
                            <input name="addDescription[]" type="text" class="form-control" value = "" required/>
                        </td>
                        <td>
                            <input name="addAmount[]" type="text" class="form-control" step="any" value = "" required/>
                        </td>
                        <td>
                            <button id="DeleteAdd" type="button" class="btn btn-danger btn-icon btn-delete-add"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>`;
                    $('#addBody').append(row);
                }
            }
        });
    </script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
@endsection