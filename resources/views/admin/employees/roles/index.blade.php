@extends('layout.app')
@section('header')
    - Roles / Positions
@endsection
@section('title')
    Roles / Positions
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Roles</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
        title="Role"
    >
    </x-general.search-table>
    @if ($access->rl_create)
        <a href="{{route('roles.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="tblroles">
                        <thead>
                            <tr>
                                <th style="">Name</th>
                                <th style=""></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                @if ($role->name == "BIR")

                                @else
                                    @if ($role->name === "OWNER" || $role->name === "BAGGER")

                                    @else
                                        <tr>
                                            <td>{{$role->name}}</td>
                                            <td>
                                            <div class="d-flex justify-content-end">
                                                @if ($access->rl_update)
                                                    <a href="{{route('roles.edit', $role->id)}}" class="btn btn-sm me-3 btn-icon btn-bg-light btn-active-color-info"><i class="fa fa-pencil-alt" aria-hidden="true"></i></a>
                                                @endif
                                                @if ($access->rl_delete)
                                                    <form action="{{ route('roles.destroy', $role->id) }}" method="DELETE" >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" onclick='return confirm("Are you sure you want to delete this role?")' class="btn btn-sm me-3 btn-icon btn-bg-light btn-active-color-danger"><i class="fa fa-trash-alt" aria-hidden="true"></i></button>
                                                    </form>
                                                @endif
                                            </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach
                        </tbody>
                    </table>
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
    let table = $("#tblroles")
    $option = {
      "responsive": true, "lengthChange": false, "autoWidth": false,
    };
    $('#tableSearch').keyup(function(){
        $("#tblroles").DataTable().search($(this).val()).draw();
    });
    let dataTable = table.DataTable($option);
    const documentTitle = 'Employee Roles';
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
  });
</script>
@endsection
