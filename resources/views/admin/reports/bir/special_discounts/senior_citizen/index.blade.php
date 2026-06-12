@extends('layout.app')
@section('header')
    - Senior Citizen Book
@endsection
@section('title')
    Senior Citizen Book
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Senior Citizen</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table title="Search Table"></x-general.search-table>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <x-data-table.table
                        table-id="storesTable"
                >
                    <th>POS Name</th>
                    <th>Terminal #</th>
                    <th></th>
                </x-data-table.table>
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
        $(document).ready(function(){
            let table = $("#storesTable");

            $option = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                order: [[4, 'asc']],
                columns: [
                    {data: 'name'},
                    {data: 'number'},
                    {data: 'number'},
                ],
                columnDefs: [
                    {
                        targets: -1,
                        render: function (data, type, full) {
                            return `
                                <div class="d-flex justify-content-end">
                                    <a href="/admin/reports/bir/special_discounts/senior/${full.id}" target="_blank" class="btn btn-icon btn-info btn-sm">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            `
                        }
                    }
                ],
                ajax: {
                    type: "GET",
                    dataSrc: function (response){
                        return response.data.original.data
                    },
                    url: '{{ route('pos.table') }}'
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

            $('#tableSearch').keyup(function(){
                table.DataTable().search($(this).val()).draw();
            });
        });
    </script>
@endsection
