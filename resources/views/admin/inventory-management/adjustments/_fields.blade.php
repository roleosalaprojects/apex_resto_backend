<div class="card card-flush mb-7">
    <div class="card-header">
        <div class="card-title">Details</div>
    </div>
    <div class="card-body">
        <div class="form-group mb-5">
            {!! Form::label("store", "Store", ["class"=>"form-label required"]) !!}
            <select id="store_select" name="store" class="form-select {{ ($errors->has('store') ?? 'is-invalid') }}" data-control="select2" data-allow-clear="true" data-placeholder="Choose a Store" data-allow-clear="true">
                <option></option>
            </select>
            <span class="text-danger" id="storeMessage">{{$errors->has('store') ? "Store field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            {!! Form::label("reason", "Reason", ["class"=>"form-label required"]) !!}
            {!! Form::select("reason", [''=>'-','inventory-count'=>'Inventory Count', 'shelf-life'=>'Shelf Life Exceeded', 'damaged'=>'Damaged Product', 'reorder'=>'Reorder Products', 'internal'=>'Internal Use'], $selected_reason, ['class'=>'form-control '. ($errors->has('reason')? 'is-invalid' : ''), 'autocomplete'=>'off']) !!}
            <span class="text-danger">{{$errors->has('reason') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            {!! Form::label("note", "Note", ["class"=>"form-label"]) !!}
            {!! Form::text("note", $adjustment->note, ["class"=>'form-control']) !!}
        </div>
    </div>
</div>

<div class="card card-flush">
    <div class="card-header">
        <div class="card-title">Items to be Adjusted</div>
    </div>
    <div class="card-body">
        <table class="table table-hover" id="itemTable">
            <thead>
                <tr>
                    <td>Name</td>
                    <td>Unit</td>
                    <td>Stocks</td>
                    <td>To adjust</td>
                    <td>Actions</td>
                </tr>
            </thead>
            <tbody id="itemsBody">
                {{-- Search Items from here --}}
                @if (Route::current()->getName() == 'adjustments.edit')
                    {!! $output !!}
                @endif
            </tbody>
            <tfoot>
                <th colspan="5">
                    <div class="form-group">
                        <select name="search" id="search" class="form-control" data-select="select2" data-placeholder="Select Item" data-allow-clear="true">
                        </select>
                    </div>
                </th>
            </tfoot>
        </table>
    </div>
</div>
@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection
@section('vendor-scripts')
    {{-- DataTables --}}
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
<script type="text/javascript">
    $(document).ready(function(){
        // Initialize Variables
        $ids = $("input[name='item_id[]']").map(function(){
                    return $(this).val();
                }).get();
        // Initialize Datatable
        $options = {
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            filter: false,
            searching: false,
            ordering:false,
            paging: false,
        };
        var table = $(".table");
        table.DataTable($options);
        // Initialize Select2
        $('#store_select').select2({
            width: "100%",
            ajax: {
                url: '{{route("stores.select")}}',
                delay: 250,
                type: "get",
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
        });
        $('#search').select2({
            ajax: {
                url: '{{route("items.select")}}',
                delay: 250,
                type: "get",
                dataType: 'json',
                data: function (params) {
                    var query = {
                        term: params.term,
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
        })
        // Initialize Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 6000
        });
        // Event Handlers
        $("#search").on('select2:select', function(){
            $name = $(this).val();
            $store = $("#store_select").val();
            var offset = findItem(name);
            if($("#store_select").val()){
                if($(this).val()){
                    if(offset === 0){
                        $.ajax({
                            type : 'get',
                            url : '{{route("adjustments.item.get")}}',
                            data:{
                                'name':$name,
                                'store':$store,
                            },
                            success:function(data){
                                $('.table').DataTable().row.add(data).draw();
                                $ids = $("input[name='item_id[]']").map(function(){
                                        return $(this).val();
                                    }).get();
                            },error:function(data){
                                console.log(data);
                            }
                        });
                    }else{
                        alertWarning();
                    }
                }
            }else{
                $("#storeMessage").html("Please select a store before adding an item.")
                $('#store_select').addClass('is-invalid');
            }
            $(this).val(null).trigger('change');
        });
        $('#store_select').on('change', function(e){
            $store_id = $(this).val();
            $("#storeMessage").html(null)
            $('#store_select').removeClass('is-invalid');
            console.log($ids);
            if($ids.length > 0){
                $.ajax({
                    url:  '{{ route("adjustments.table.item") }}',
                    data: {
                        'ids': $ids,
                        'store': $store_id,
                    },
                    success: function(response){
                        var data = table.DataTable().rows().data();
                        // var server = JSON.parse(response);
                        data.each(function (value, index) {
                            var colIndex = 2;
                            table.DataTable().cell(index, colIndex).data(response[index]["stock"]);
                        });
                    }
                });
            }
        });
        $("#itemTable").on("click", "#DeleteButton", function() {
            table.DataTable().row($(this).parents('tr')).remove().draw();
        });
        @if(Route::current()->getName() == 'adjustments.edit')
            // Source Select
            $.ajax({
                type:"get",
                url: '{{ route("store.get", $adjustment->store_id) }}'
            }).then(function (response){
                var data = response;
                var option = new Option(data.name, data.id, true, true);
                var select = $("#store_select");
                select.append(option).trigger('change');
                select.trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            })
        @endif
    });
    function findItem(name){
        var offset = 0;
        $("#itemTable tbody tr").each(function() {
            var item = $(this).find(" td:nth-child(1)").text();
            if(item == name){
                offset++;
            }
        });
        return offset;
    }

    </script>
<script type="text/javascript">
    $.ajaxSetup({ headers: { 'csrftoken' : '{{ csrf_token() }}' } });
</script>
@endsection
