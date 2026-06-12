@extends('layout.app')
@section('header')
    - Purchase Orders
@endsection
@section('title')
    Purchase Orders
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Purchase Orders</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
            title="PO"
    ></x-general.search-table>
    @if ($access->prchs_create)
        <a href="{{route('purchases.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="text-justify">
                    </div>
                    <x-data-table.table
                            table-id="purchasesTable"
                    >
                        <th>PO#</th>
                        <th>Supplier</th>
                        <th>Destination</th>
                        <th>Purchase Date</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Payment</th>
                        <th>Invoice #</th>
                        <th>Total</th>
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
            $startDate = moment().startOf('day');
            $endDate = moment().endOf('day');
            $option = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {'data': 'po'},
                    {'data': 'supplier.name'},
                    {'data': 'store.name'},
                    {'data': "purchased"},
                    {'data': 'status'},
                    {'data': 'approval_status'},
                    {'data': 'payment_status'},
                    {'data': "invoice_no"},
                    {'data': "total"},
                    {'data': 'actions'}
                ],
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                    },
                    {
                        targets: -2,
                        render: function (data, type, full) {
                            return accountingFormat(full.total);
                        }
                    },
                    {
                        targets: 4,
                        render: function (data, type, full) {
                            var progress = "";
                            var style = "";
                            var width = 0;
                            if (full.items - full.received > 0) {
                                style = "bg-info";
                            } else if (full.items - full.received == 0) {
                                style = "bg-success";
                            } else {
                                style = "bg-primary";
                            }
                            if (full.received >= 0) {
                                width = (full.received / full.items) * 100;

                            }
                            progress = '<div class="progress progress-xs" style="height: 7px;">\
                            <div class="progress-bar ' + style + ' progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: ' + width + '%"></div>\
                        </div>\
                        <small>' + full.received + ' of ' + full.items + ' received</small>';
                            return progress;
                        }
                    },
                    {
                        targets: 5,
                        render: function (data, type, full) {
                            var badge = '';
                            switch (full.approval_status) {
                                case 0:
                                    badge = '<span class="badge badge-secondary">Draft</span>';
                                    break;
                                case 1:
                                    badge = '<span class="badge badge-warning">Pending</span>';
                                    break;
                                case 2:
                                    badge = '<span class="badge badge-success">Approved</span>';
                                    break;
                                case 3:
                                    badge = '<span class="badge badge-danger">Rejected</span>';
                                    break;
                                default:
                                    badge = '<span class="badge badge-secondary">Unknown</span>';
                            }
                            return badge;
                        }
                    },
                    {
                        targets: 6,
                        render: function (data, type, full) {
                            var badge = '';
                            switch (full.payment_status) {
                                case 0:
                                    badge = '<span class="badge badge-danger">Unpaid</span>';
                                    break;
                                case 1:
                                    badge = '<span class="badge badge-warning">Partial</span>';
                                    break;
                                case 2:
                                    badge = '<span class="badge badge-success">Paid</span>';
                                    break;
                                default:
                                    badge = '<span class="badge badge-secondary">Unknown</span>';
                            }
                            return badge;
                        }
                    },
                ],
                ajax: {
                    data: {
                        'startDate': function () {
                            return $startDate
                        },
                        'endDate': function () {
                            return $endDate
                        },
                    },
                    url: '{{ route('purchases.table') }}'
                }
            };
            $('#daterangepicker').daterangepicker({
                startDate: $startDate,
                endDate: $endDate,
                ranges: {
                    "Today": [moment(), moment()],
                    "Yesterday": [moment().subtract(1, "days"), moment().subtract(1, "days")],
                    "Last 7 Days": [moment().subtract(6, "days"), moment()],
                    "Last 30 Days": [moment().subtract(29, "days"), moment()],
                    "This Month": [moment().startOf("month"), moment().endOf("month")],
                    "Last Month": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")],
                    "This Year": [moment().subtract(1, "year").startOf("year"), moment().subtract(1, "year").endOf("year")],
                    "Last Year": [moment().subtract(2, "year").startOf("year"), moment().subtract(2, "year").endOf("year")],
                }
            }, function (start, end, label) {
                $startDate = start.format("YYYY-MM-DD");
                $endDate = end.format("YYYY-MM-DD");
                table.DataTable().ajax.reload();
            });
            let table = $("#purchasesTable");
            let dataTable = table.DataTable($option);
            const documentTitle = 'Purchase Order Records';
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
                console.log(e);
                var name = $("#name_" + id).val();
                $('#customer_name').html(name);
                $('#confirm_delete').attr('form', 'form_delete_' + id)
            });
        });
    </script>
@endsection
