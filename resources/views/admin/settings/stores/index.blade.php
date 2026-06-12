@extends('layout.app')
@section('header')
    - Stores
@endsection
@section('title')
    Stores
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Stores</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table title="Location"></x-general.search-table>
    @if ($access->str_create)
        <a href="{{route('stores.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table
                        table-id="storesTable"
                    >
                        <th>Name</th>
                        <th>TIN</th>
                        <th>Branch Number</th>
                        <th>Phone #</th>
                        <th>Email</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal for Deletion --}}
    <div class="modal fade" tabindex="-1" id="deleteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Delete Store</h5>

                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                    <!--end::Close-->
                </div>

                <div class="modal-body">
                    <h5 id="store_name">Name Here</h5>
                    <label class="form-label">Are you sure you want to delete this Store?</label>
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
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
<script>
  $(document).ready(function(){
    let table = $("#storesTable");
    $option = {
        filter: true,
        responsive: true,
        serverside: true,
        processing: true,
        columns: [
            {data: 'name'},
            {data: 'tin'},
            {data: 'counter'},
            {data: 'phone'},
            {data: 'email'},
            {data: 'actions'},
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, full){
                    const badge = (full.vat_reg) ? `<span class="badge badge-success">VAT Registered</span>` : `<span class="badge badge-primary">Non VAT</span>`;
                    return full.name + " " + badge;
                }
            },
            {
                targets: -1,
                orderable: false,
            }
        ],
        ajax: {
            dataSrc: function (response){
                return response.data
            },
            url: '{{ route('stores.table') }}'
        },
    }
    let dataTable = table.DataTable($option);
    const documentTitle = 'Locations Listing';
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
        var id = $(this).val();
        var name = $("#name_"+id).val();
        $('#store_name').html(name);
        $('#confirm_delete').attr('form','form_delete_'+id)
    });
    $('#tableSearch').keyup(function(){
        table.DataTable().search($(this).val()).draw();
    });
  });
</script>
@endsection
