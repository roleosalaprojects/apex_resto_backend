<div class="card card-flush mb-7">
    <div class="card-header">
        <div class="card-title">Details</div>
    </div>
    <div class="card-body">
        <div class="form-group mb-5">
            {!! Form::label("store", "Select Store", []) !!}
            <select id="store_select" name="store" class="form-select {{ ($errors->has('store') ?? 'is-invalid') }}" data-control="select2" data-allow-clear="true" data-placeholder="Choose a Store">
                <option></option>
            </select>
            <span class="text-danger" id="storeMessage">{{$errors->has('store') ? "Store field cannot be empty!" : ''}}</span>
            @error('store')
                <span class="text-danger">{{$message}}</span>
            @enderror
        </div>
        <div class="form-group mb-5">
            {!! Form::label("note", "Notes", []) !!}
            {!! Form::text("note", "", ["class"=>'form-control']) !!}
        </div>
    </div>
</div>
<div class="card card-flush">
    <div class="card-header">
        <div class="card-title">Items / Products</div>
    </div>
    <div class="card-body">
        <table class="table table-hover" id="tableItems">
            <thead>
                <th>Item</th>
                <th>Unit</th>
                <th>Stocks</th>
                <th>Actions</th>
            </thead>
            <tbody>

            </tbody>
            <tfoot>
                <th colspan="4">
                    <div class="form-group">
                        <select name="search" id="search" class="form-select  {{($errors->has('item_id')) ? " is-invalid" : ""}}" data-placeholder="Select Item / Product" data-allow-clear="true">
                            {{-- Search Items from here --}}
                        </select>
                        @error('item_id')
                            <span class="text-danger">{{$message}}</span>
                        @enderror
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
<script>
    $(function(){
        // Initialize Variables
        $ids = $("input[name='item_id[]']").map(function(){
                return $(this).val();
            }).get();
        // Initialize Select
        $('#store_select').select2({
            width: '100%',
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
        $("#search").select2({
            width: '100%',
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
        });
        // Initialize DataTable
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
        // Event Handlers
        $("#search").on('select2:select', function(){
            $name = $(this).val();
            var offset = findItem(name);
            if($("#store_select").val()){
                if($(this).val()){
                    if(offset === 0){
                        $.ajax({
                            type : 'get',
                            url : '{{route("ic-get-item")}}',
                            data:{
                                'name':$name,
                                'store': $("#store_select").val(),
                            },
                            success:function(data){
                                console.log(data);
                                table.DataTable().row.add(data).draw();
                                $ids = $("input[name='item_id[]']").map(function(){
                                    return $(this).val();
                                }).get();
                                console.log($ids);
                            },
                            error:function(data){
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
            if($ids.length > 0){
                $.ajax({
                    url:  '{{ route("ic.table.items") }}',
                    data: {
                        'ids': $ids,
                        'store': $store_id,
                    },
                    success: function(response){
                        console.log(response);
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
        $("#tableItems").on("click", "#DeleteButton", function() {
            table.DataTable().row($(this).parents('tr')).remove().draw();
        });
        $('#search').on('keyup',function(e){
            clearTimeout($.data(this, 'timer'));
            $value=$(this).val();
            var wait = setTimeout(fetchData($value), 1500);
            $(this).data('timer', wait);
        });
        function fetchData($value){
            $.ajax({
                type : 'get',
                url : '{{route("items.select")}}',
                data:{'name':$value},
                success:function(data){
                    // console.log($value);
                    // console.log(data);
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
        var optionals = 0;
        function checkStore() {
            var s = $('#sourceStore').val();
            var d = $('#destinationStore').val();
            if(s === d){
                optionals++;
                $('#sourceStore').addClass('is-invalid')
                $('#destinationStore').addClass('is-invalid')
                toastr.error('Source aand destination store cannot be the same!')
            }else{
                $('#sourceStore').removeClass('is-invalid')
                $('#destinationStore').removeClass('is-invalid')
            }
        }
    })
</script>
<script type="text/javascript">
    $.ajaxSetup({ headers: { 'csrftoken' : '{{ csrf_token() }}' } });
</script>
@endsection
