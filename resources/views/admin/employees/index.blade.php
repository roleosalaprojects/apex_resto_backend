@extends('layout.app')
@section('header')
    - Employees Listing
@endsection
@section('title')
    Employees Listing
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Employee List</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
        title="User"
    ></x-general.search-table>
    @if (auth()->user()->role->emplys_create)
        <a href="{{route('employees.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table
                        table-id="tblEmployees"
                    >
                        <th>Name</th>
                        <th>Barcode</th>
                        <th>Position</th>
                        {{-- <th>Schedule</th> --}}
                        {{-- <th>Sales Today</th> --}}
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
                <h5 class="modal-title" id="modal-title">Delete Employee</h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <h5 id="employee_name">Name Here</h5>
                <label class="form-label">Are you sure you want to delete this Employee?</label>
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
  $(function () {
    let table = $("#tblEmployees");
    $option = {
        filter: true,
        responsive: true,
        serverside: true,
        processing: true,
        ajax: {
            url: "{{route('employees.table')}}",
            dataSrc: function(response){
                return response.data;
            }
        },
        columns: [
            {'data': 'name'},
            {'data': 'code'},
            {'data': 'position'},
            {'data': 'actions'},
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, full) {
                    console.log(full);
                    var image = "{{asset('assets/media/avatars/blank.png')}}";
                    if(full.details.image){
                        image = "{{asset('/')}}" + full.details.image
                    }
                    return '\
                        <div class="d-flex align-items-center">\
                            <div class="symbol symbol-40 symbol-sm flex-shrink-0 me-4">\
                                <img src="'+image+'" class="img-circle elevation-2" style="width: 50px; height: 50px" alt="photo">\
                            </div>\
                            <div class="ml-4">\
                                <div class="text-dark-75 font-weight-bolder font-size-lg mb-0">' + full.name + '</div>\
                            </div>\
                        </div>\
                    ';
                }
            },
            {
                targets: 1,
                render: function(data, type, full){
                    return full.code ? full.code : '<span class="text-muted">-</span>';
                }
            },
            {
                targets: 2,
                render: function(data, type, full){
                    return (full.position) ? full.position.name : "N/A";
                }
            },
            {
                targets: 3,
                orderable: false,
            }
        ],
    };

    let dataTable = table.DataTable($option);
    const documentTitle = 'Employees Listing';
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
        $('#category_name').html(name);
        $('#confirm_delete').attr('form','form_delete_'+id)
    });

    $('#tableSearch').keyup(function(){
        table.DataTable().search($(this).val()).draw();
    });
  });
</script>
@endsection
