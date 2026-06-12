<div class="row mb-10">
    <div class="col">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">Transfer Information</div>
            </div>
            <div class="card-body">
                <div class="form-group mb-5 fv-row">
                    {!! Form::label('source_store', 'Source Location', ["class"=>"form-label required"]) !!}
                    <select id="source_store" name="source_store" class="form-select {{ ($errors->has('source_store') ?? 'is-invalid') }}" data-control="select2" data-allow-clear="true" data-placeholder="Choose a Source">
                        <option></option>
                    </select>
                    <span class="text-danger" id="sourceMessage">{{($errors->has('source_store') ? 'Source store is required' : '')}}</span>
                </div>
                <div class="form-group mb-5 fv-row">
                    {!! Form::label('destination_store', 'Destination Location', ["class"=>"form-label required"]) !!}
                    <select id="destination_store" name="destination_store" class="form-select {{ ($errors->has('destination_store') ?? 'is-invalid') }}" data-control="select2" data-allow-clear="true" data-placeholder="Choose a Destination">
                        <option></option>
                    </select>
                    <span class="text-danger" id="destinationMessage">{{($errors->has('destination_store') ? 'Destination store is required' : '')}}</span>
                </div>
                <div class="form-group mb-5 fv-row">
                    <label for="" class="form-label">Delivery Personnel</label>
                    <input type="text" name="delivery" class="form-control">
                </div>
                <div class="form-group mb-5">
                    {!! Form::label('note', 'Note', ["class"=>"form-label"]) !!}
                    {!! Form::text('note', '', ['class'=>'form-control ']) !!}
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    Items
                </div>
            </div>
            <div class="card-body">
                <table class="table table-hover" id="tableItems">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Unit</th>
                            <th>Source Stock</th>
                            <th>Destination Stock</th>
                            <th>To transfer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        @if (Route::current()->getName() == 'transfers.edit')
                            @foreach ($transfer->lines as $line)
                                <tr>
                                    <td>
                                        <input type='hidden' name='item_id[]' value='{{ $line->item_id }}'/>
                                        {{ $line->item->name }}
                                    </td>
                                    <td>
                                        <select name='unit[]' class='form-select'>
                                            <option value='0'>PCS</option>
                                            @foreach ($line->item->itemUnits as $item_unit)
                                                <option value="{{ $item_unit->unit->id }}" {{ ($line->unit_id == $item_unit->unit->id) ? "selected" : 'ambot' }}>{{ $item_unit->unit->name}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @foreach ($line->item->stocks as $source_stock)
                                            {{ ($source_stock->store_id == $transfer->source_store) ? $source_stock->stock : '' }}
                                        @endforeach
                                    </td>
                                    <td>
                                        @foreach ($line->item->stocks as $source_stock)
                                            {{ ($source_stock->store_id == $transfer->destination_store) ? $source_stock->stock : '' }}
                                        @endforeach
                                    </td>
                                    <td><input name='qty[]' type='text' class='form-control' onkeypress='return isNumberKey(event)' oninput='limitDecimalPlaces(event, 2)' value = '{{ $line->qty }}' required/></td>
                                    <td><button id="DeleteButton" type="button" class="btn btn-danger btn-icon"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6">
                                <div class="form-group">
                                    <select name="search" id="search" class="form-control" data-control="select2" data-placeholder="Search Item / Product" style="width: 100%;">
                                        <option value=""></option>
                                    </select>
                                </div>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script type="text/javascript">
        $(document).ready(function(){
            $ids = $("input[name='item_id[]']").map(function(){
                        return $(this).val();
                    }).get();
            //  Initialize DataTable
            $options = {
                responsive: true,
                lengthChange: false,
                autoWidth: false,
                filter: false,
                searching: false,
                ordering:false,
                paging: false,
            };
            var table = $("#tableItems");
            table.DataTable($options);
            // Initialize Select2
            $('#search').select2({
                allowClear: true,
                ajax: {
                    url: '{{ route('items.select') }}',
                    delay: 250,
                    type: "GET",
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            term: params.term,
                        }
                        return query;
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                }
            })
            $("#source_store").select2({
                width: "100%",
                ajax: {
                    url: '{{ route('stores.select') }}',
                    delay: 250,
                    type: "get",
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            search: params.term,
                        }
                        return query;
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                }
            });
            $("#destination_store").select2({
                width: "100%",
                ajax: {
                    url: '{{ route('stores.select') }}',
                    delay: 250,
                    type: "get",
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            search: params.term,
                        }
                        return query;
                    },
                    processResults: function (data) {
                        // console.log(data);
                        return {
                            results: data
                        };
                    },
                    cache: true
                }
            });
            @if (Route::current()->getName() == "transfers.edit")
                // Source Select
                $.ajax({
                    type:"get",
                    url: '{{ route("store.get", $transfer->source_store) }}'
                }).then(function (response){
                    var data = response;
                    var option = new Option(data.name, data.id, true, true);
                    var select = $("#source_store");
                    select.append(option).trigger('change');
                    select.trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                })// Destination Select
                $.ajax({
                    type:"get",
                    url: '{{ route("store.get", $transfer->destination_store) }}'
                }).then(function (response){
                    var data = response;
                    var option = new Option(data.name, data.id, true, true);
                    var select = $("#destination_store");
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
            $("input[name=itemSelect]").on('change', function(){
                var name = $(this).val();
                $(this).val(null).trigger('change');
                var offset = findItem(name);
                if(offset === 0){
                    var cost = "<input type='text' name='cost[]' class='form-control' onkeypress='return isNumberKey(event)' oninput='limitDecimalPlaces(event, 2)' value = '' onchange='calculate()' required/>";
                    var qty = "<input type='text' name='qty[]' class='form-control' onkeypress='return isNumberKey(event)' oninput='limitDecimalPlaces(event, 2)' value = '' onchange='calculate()' required/>";
                    // console.log(cost);
                    $('#itemTable').row.add('<tr><td>'+qty+'</td><td>'+cost+'</td><td>0</td><td><button id="DeleteButton" type="button" class="btn btn-danger btn-flat"><i class="fas fa-trash-alt"></i></button></td></tr>');
                }else{
                    alertWarning();
                }
            });
            $("#search").on('select2:select', function(){
                $name = $(this).val();
                var offset = findItem(name);
                if(checkStore() && validateStore($("#source_store").val(), $("#destination_store").val()) && $(this).val()){
                    if(offset === 0){
                        $.ajax({
                            type : 'get',
                            url : '{{ route('transfer.get.item') }}',
                            data:{
                                'name': $name,
                                'source_store': $("#source_store").val(),
                                'destination_store': $("#destination_store").val(),
                            },
                            success:function(data){
                                table.DataTable().row.add(data).draw();
                                $ids = $("input[name='item_id[]']").map(function(){
                                        return $(this).val();
                                    }).get();
                            },error:function(data){
                                toastr.error('Something went wrong.')
                            }
                        });
                    }else{
                        alertWarning();
                    }
                    $(this).val(null).trigger('change');
                }
            });
            $("#source_store").on("change", function(e){
                $id = $(this).val();
                if(!validateStore($id, $("#destination_store").val())){
                    toastr.error('Source and Destination Stores cannot be the same!')
                }else{
                    $("#sourceMessage").html(null);
                    $('#source_store').removeClass('is-invalid');
                    updateTable();
                }
            })
            $("#destination_store").on("change", function(e){
                $id = $(this).val();
                if(!validateStore($id, $("#source_store").val())){
                    toastr.error('Source and Destination Stores cannot be the same!')
                }else{
                    $("#destinationMessage").html(null);
                    $('#destination_store').removeClass('is-invalid')
                    updateTable();
                }
            })
            $("#tableItems").on("click", "#DeleteButton", function() {
                table.DataTable().row($(this).parents('tr')).remove().draw();
            });
        });
        // Function Declarations
        var optionals = 0;
        function validateStore(source, destination) {
            if(source == destination){
                optionals++;
                $('#destination_store').addClass('is-invalid')
                $('#source_store').addClass('is-invalid')
                return false;
            }else{
                $('#destination_store').removeClass('is-invalid')
                $('#source_store').removeClass('is-invalid')
                return true;
            }
        }
        function checkStore(){
            $source = $("#source_store").val();
            $destination = $("#destination_store").val();
            if($source && $destination){
                return true;
            }else{
                if(!$source){
                    $('#source_store').addClass('is-invalid')
                    $("#sourceMessage").html("Select a Source first.");
                }
                if(!$destination){
                    $("#destinationMessage").html("Select a Destination first.");
                    $('#destination_store').addClass('is-invalid')
                }
                return false;
            }
        }
        function updateTable(){
            var table = $("#tableItems");
            if($ids.length > 0 && checkStore()){
                $.ajax({
                    url: "#",
                    data: {
                        'ids': $ids,
                        'source': $("#source_store").val(),
                        'destination': $("#destination_store").val(),
                    },
                    success: function(response){
                        var data = table.DataTable().rows().data();
                        data.each(function (value, index) {
                            var sourceIndex = 2;
                            var destinationIndex = 3;
                            table.DataTable().cell(index, sourceIndex).data(response[index]["source"]);
                            table.DataTable().cell(index, destinationIndex).data(response[index]["destination"]);
                        });
                    }
                });
            }
        }
        function fetchData($value){
            $.ajax({
                type : 'get',
                url : '#',
                data:{'name':$value},
                success:function(data){
                    $('#items').html(data);
                },
                error: function(data){
                    console.log(data);
                }
            });
            // console.log($value);
        }
        function delay(callback, ms) {
            var timer = 0;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () {
                callback.apply(context, args);
                }, ms || 1000);
            };
        }
        function findItem(name){
            var offset = 0;
            $("#itemTable tbody tr").each(function() {
                var item = $(this).find("td:nth-child(1)").text();
                if(item == name){
                    offset++;
                }
            });
            return offset;
        }
        function alertWarning() {   
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 6000
        });
        toastr.error('Item already exists!')
        }
    </script>
    <script type="text/javascript">
        $.ajaxSetup({ headers: { 'csrftoken' : '{{ csrf_token() }}' } });
    </script>
@endsection