@extends('layout.app')
@section('header')
    - Taxes
@endsection
@section('title')
    Tax Management
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item active">Taxes</li>
@endsection
@section('actions')
    <x-data-table.actions>
    </x-data-table.actions>
    <x-general.search-table
        title="Tax"
    ></x-general.search-table>
    @if ($access->tax_create)
        <a href="{{route('taxes.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table
                        table-id="taxesTable"
                    >
                        <th>Name</th>
                        <th>Rate</th>
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
                    <h5 class="modal-title" id="modal-title">Delete Tax</h5>

                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                    <!--end::Close-->
                </div>

                <div class="modal-body">
                    <h5 id="tax_name">Name Here</h5>
                    <label class="form-label">Are you sure you want to delete this Tax?</label>
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
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
<script>
  $(document).ready(function(){
    let table = $("#taxesTable");
    $option = {
        filter: true,
        responsive: true,
        serverside: true,
        processing: true,
        columns: [
            {data: 'name'},
            {data: 'rate'},
            {data: 'actions'},
        ],
        columnDefs: [
            {
                targets: 1,
                orderable: false,
            }
        ],
        ajax: {
            dataSrc: function (response){
                return response.data
            },
            url: '{{ route("taxes.table") }}'
        },
    }
    let dataTable = table.DataTable($option);
    const documentTitle = 'Taxes Records';
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
        $('#tax_name').html(name);
        $('#confirm_delete').attr('form','form_delete_'+id)
    });
    $('#tableSearch').keyup(function(){
        table.DataTable().search($(this).val()).draw();
    });
  })
</script>
@endsection
