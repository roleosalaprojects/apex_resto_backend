@extends('layout.app')
@section('header')
    - Products Listing
@endsection
@section('title')
    Items / Products
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item  text-muted">Items</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
        title="Product"
    ></x-general.search-table>
    @if (auth()->user()->role->itms_create)
        <a href="{{ route('items.create') }}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card card-flush">
                <div class="card-body">
                        <x-general.data-table
                            table-id="tblItems"
                        >
                            <th>Name</th>
                            <th>Barcode</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Stocks</th>
                            <th></th>
                        </x-general.data-table>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal for Deletion --}}
    <div class="modal fade" tabindex="-1" id="deleteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Delete Item / Product</h5>

                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                    <!--end::Close-->
                </div>

                <div class="modal-body">
                    <h5 id="item_name">Name Here</h5>
                    <label class="form-label">Are you sure you want to delete this Item / Product?</label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="confirm_delete" class="btn btn-danger font-weight-bold" form="">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection
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
        let table = $("#tblItems");
         $options = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5',
            ],
            ajax: {
                url: "{{ route('items.table') }}",
                data: {
                    user: {{auth()->user()->user_id}}
                },
                dataSrc: function(response){
                    return response.data;
                }
            },
            columns: [
                {'data': 'name'},
                {'data': 'barcode'},
                {'data': 'price'},
                {'data': 'category'},
                {'data': 'supplier'},
                {'data': 'stocks'},
                {'data': 'actions'}
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function (data, type, full){
                        return `<div class="d-flex align-items-center">
                                    ${productImage(full.image)}
                                    <div class="ms-5">
                                        <span class="fw-bold">
                                            ${full.name}
                                        </span>
                                    </div>
                                </div>`;
                    }
                },
                {
                    targets: 1,
                    orderable: false,
                },
                {
                    targets: 3,
                    render: function (data, type, full) {
                        return (full.category) ? full.category.name : "N/A";
                    }
                },
                {
                    targets: 4,
                    render: function (data, type, full) {
                        return (full.supplier) ? full.supplier.name : "N/A";
                    }
                },
                {
                    targets: 5,
                    title: "Stocks",
                    render: function(data, type, full ,meta){
                        var inventory = full.stocks.map(obj => (
                            {
                                stock: obj.stock,
                                store: obj.store,
                            }
                        ));
                        var column = "";
                        inventory.map(function(element){
                            var store = (element.store) ? element.store["name"] : "N/A"
                            column += `<em>${store}</em>:&nbsp;<span class="text-info">${element.stock}</span>&nbsp;<br>`;
                        });
                        return column;
                    }
                },
                {
                    targets: 2,
                    render: function(data, type, full){
                        let price = full.price;
                        let label = "text-success";
                        if(price === 0)
                        {
                            price = full.cost + (full.cost * (full.markup/100));
                            label = "text-danger";
                        }
                        return '\
                            <span class="'+label+'">'+price.toFixed(2)+'</span>\
                        ';
                    }
                }
            ]
        }

        let dataTable = table.DataTable($options);
        const documentTitle = 'Item/Products Listing';
        var buttons = new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'copyHtml5',
                    title: documentTitle
                },
                {
                    extend: 'excelHtml5',
                    title: documentTitle
                },
                {
                    extend: 'csvHtml5',
                    title: documentTitle
                },
                {
                    extend: 'pdfHtml5',
                    title: documentTitle
                }
            ]
        }).container().appendTo($('#datatable_buttons'));

        // Hook dropdown menu click event to datatable export buttons
        const exportButtons = document.querySelectorAll('#datatables_menu [data-kt-export]');
        exportButtons.forEach(exportButton => {
            exportButton.addEventListener('click', e => {
                e.preventDefault();

                // Get clicked export value
                const exportValue = e.target.getAttribute('data-kt-export');
                const target = document.querySelector('.dt-buttons .buttons-' + exportValue);

                // Trigger click event on hidden datatable export buttons
                target.click();
            });
        });

        table.on('click', '.btn-active-color-danger', function(e){
            let id = $(this).val();
            let name = $("#name_"+id).val();
            $('#item_name').html(name);
            $('#confirm_delete').attr('form','form_delete_'+id)
        });
        $('#tableSearch').keyup(function(){
            table.DataTable().search($(this).val()).draw();
        });

        function productImage(imageUrl){
            // Get the current full URL
            let currentUrl = window.location.origin;
            if(!imageUrl){
                imageUrl = 'assets/media/svg/general/rhone.svg';
            }
            if (!currentUrl.endsWith('/')) {
                currentUrl += '/';
            }
            if (!currentUrl.endsWith('/')) {
                currentUrl += '/';
            }
            return `<div class="symbol symbol-50px">
                        <div class="symbol-label" style="background-image:url('${currentUrl + imageUrl}')"></div>
                    </div>`
        }
    });
</script>
@endsection
