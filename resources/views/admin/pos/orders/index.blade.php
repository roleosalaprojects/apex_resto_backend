@extends('layout.app')
@section('header')
    Orders
@endsection
@section('title')
    Sales Orders
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Orders Management</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
        title="Order"
    ></x-general.search-table>
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card card-custom">
                <div class="card-body">
                    <table class="table table-responsive" id="tblOrders">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Created By</th>
                                <th>POS</th>
                                <th>QTY</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Creation Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
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
            // Moment
            $startDate = moment().startOf('day');
            $endDate = moment().endOf('day');
            // Select2
            var store_select = $("#store_select");

            // Init Select2
            store_select.select2({
                dropdownParent: $("#card_actions"),
                ajax: {
                    url: "{{ route('stores.select') }}",
                    type: "GET",
                    delay: 250,
                    dataType: "JSON",
                    data: function (params) {
                        var queryParameters = {
                            term: params.term
                        }
                        return queryParameters;
                    },
                    processResults: function (data) {
                        // console.log(data);
                        return {
                            results: data
                        };
                    },
                }
            })

            // DataTable Elements
            let table = $("#tblOrders");
            let options = {
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
                    url: "{{ route('orders.table') }}",
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                    },
                    dataSrc: function(response){
                        return response.data;
                    }
                },
                columns: [
                    {'data': 'reference'},
                    {'data': 'creator.name'},
                    {'data': 'pos'},
                    {'data': 'qty'},
                    {'data': 'amount'},
                    {'data': 'status'},
                    {'data': 'created_at'},
                    {'data': 'actions'}
                ],
                columnDefs: [
                    {
                        targets: 2,
                        render: function (data, type, full){
                            return full.pos ? full.pos.name : 'Mobile Order';
                        }
                    },
                    {
                        targets: -4,
                        render: function(data, type, full){
                            return numberWithCommas(full.amount);
                        }
                    },
                    {
                        targets: -3,
                        render: function(data, type, full){
                            var types = {
                                0: {title: 'Pending', class: 'badge badge-light-warning'},
                                1: {title: 'Order Accepted', class: 'badge badge-light-primary'},
                                2: {title: 'Preparing', class: 'badge badge-light-info'},
                                3: {title: 'For Pickup', class: 'badge badge-light-success'},
                                4: {title: 'Completed', class: 'badge badge-light-primary'},
                                5: {title: 'Cancelled', class: 'badge badge-light-danger'},
                            }
                            return `<span class="${types[full.status].class}">${types[full.status].title}</span>`
                        }
                    },
                    {
                        targets: -2,
                        render: function(data, type, full){
                            return moment(full.created_at).format('Y-M-d h:m A')
                        }
                    }
                ]
            }
            table.DataTable(options);
            const documentTitle = `Sales Order from ${$startDate} to ${$endDate}`;
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

            $('#daterangepicker').daterangepicker({
                startDate: $startDate,
                endDate: $endDate,
                showDropdowns: true,
                ranges: {
                    "Today": [moment(), moment()],
                    "Yesterday": [moment().subtract(1, "days"), moment().subtract(1, "days")],
                    "Last 7 Days": [moment().subtract(6, "days"), moment()],
                    "Last 30 Days": [moment().subtract(29, "days"), moment()],
                    "This Month": [moment().startOf("month"), moment().endOf("month")],
                    "This Year": [moment().startOf("year"), moment().endOf("year")],
                    "Last Month": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")],
                    "Last 6 Months": [moment().subtract(6, "month").startOf("month"), moment().endOf("month")],
                    "Last Year": [moment().subtract(1, "year").startOf("year"), moment().subtract(1, "year").endOf("year")],
                }
            }, function(start, end, label) {
                $startDate = start.format("YYYY-MM-DD");
                $endDate = end.format("YYYY-MM-DD");
                return table.DataTable().ajax.reload()
            });

            store_select.on('select2:select', function(){
                table.DataTable().ajax.reload()
            })

            $('#tableSearch').keyup(function(){
                table.DataTable().search($(this).val()).draw();
            });

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
        })
    </script>
@endsection
