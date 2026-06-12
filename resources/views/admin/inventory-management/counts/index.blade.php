@extends('layout.app')
@section('header')
    - Inventory Counts
@endsection
@section('title')
    Inventory Counts
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Inventory Counts</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
            title="IC"
    ></x-general.search-table>
    @if ($access->invntry_create)
        <a href="{{route('counts.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table
                            table-id="tblCounts"
                    >
                        <th>IC #</th>
                        <th>Store</th>
                        <th>Created by</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Creation Date</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
    @include('admin.layouts.extra.delete_modal')
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
        $(document).ready(function () {
            $option = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {'data': 'ic'},
                    {'data': 'store_name'},
                    {'data': 'creator'},
                    {'data': 'items_count'},
                    {'data': 'status'},
                    {'data': 'created'},
                    {'data': 'actions'}
                ],
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                    },
                    {
                        targets: 4,
                        render: function (data, type, full) {
                            const statusLabels = {0: 'Draft', 1: 'In Progress', 2: 'Completed', 3: 'Cancelled'};
                            const statusColors = {0: 'secondary', 1: 'warning', 2: 'success', 3: 'danger'};
                            return '<span class="badge bg-' + statusColors[full.status] + '">' + statusLabels[full.status] + '</span>';
                        }
                    },
                    {
                        targets: 5,
                        render: function (data, type, full) {
                            return moment(full.created).format('MMM DD, YYYY (hh:mm A)')
                        }
                    },
                ],
                ajax: {
                    url: '{{ route('counts.table') }}'
                }
            };
            let table = $("#tblCounts");
            let dataTable = table.DataTable($option);
            const documentTitle = 'Inventory Count Records';
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
            table.on('click', '.btn-active-color-danger', function (e) {
                var id = $(this).val();
                var name = $("#name_" + id).val();
                $('#category_name').html(name);
                $('#confirm_delete').attr('form', 'form_delete_' + id)
            });
            $('#tableSearch').keyup(function () {
                table.DataTable().search($(this).val()).draw();
            });
        });
    </script>
@endsection
