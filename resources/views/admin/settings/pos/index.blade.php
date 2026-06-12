@extends('layout.app')
@section('header')
    - POS Management
@endsection
@section('title')
    POS Devices
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">POS Management</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
        title="POS"
    ></x-general.search-table>
    {{--Removed so that only developer can add a POS.--}}
{{--    @if ($access->sttngs)--}}
{{--        <a href="{{route('pos.create')}}" class="btn btn-sm btn-success">Create</a>    --}}
{{--    @endif--}}
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="tblroles">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Terminal #</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pos as $device)
                                <tr>
                                    <td>{{$device->name}}</td>
                                    <td>{{$device->number}}</td>
                                    <td>
                                        @if ($device->status)
                                            <span class="text-success">Active</span>
                                        @else
                                            <span class="text-danger">Not active</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($access->sttngs)
                                            <div class="d-flex justify-content-end">
                                                <a href="{{route('pos.edit', $device->id)}}" class="btn btn-icon btn-bg-light btn-active-color-info"><i class="fa fa-pencil-alt" aria-hidden="true"></i></a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
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
    var table = $("#tblroles")
    $option = {
        filter: true,
        responsive: true,
        serverside: true,
        processing: true,
    };
    let dataTable = table.DataTable($option);
    const documentTitle = 'POS Devices';
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
