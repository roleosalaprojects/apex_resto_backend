@extends('layout.app')
@section('header')
    - Receipts
@endsection
@section('Title')
    Receipts
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Sales Summary</li>
@endsection
@section('actions')
    <x-data-table.actions>
        <!--begin::Header-->
        <div class="px-7 py-5">
            <div class="fs-5 text-dark fw-bold">Filter Options</div>
        </div>
        <!--end::Header-->
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
        <!--begin::Form-->
        <div class="px-7 py-5">
            <!--begin::Input group-->
            <div class="mb-3">
                <!--begin::Label-->
                <label class="form-label fw-semibold">Select Store:</label>
                <!--end::Label-->
                <!--begin::Input-->
                <div>
                    <select class="form-select form-select-solid select2-hidden-accessible" id="store_select" data-kt-select2="true" data-placeholder="Select option" data-allow-clear="true" tabindex="-1" aria-hidden="true" data-kt-initialized="1">
                        <option data-select2-id="select2-data-12-htww"></option>
                    </select>
                </div>
                <!--end::Input-->
            </div>
            <!--end::Input group-->
        </div>
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
        <div class="px-7 py-5">
            <div class="mb-5">
                <label for="daterangepicker" class="form-label">Date</label>
                <input class="form-control form-control-solid form-control" placeholder="Pick date range" id="daterangepicker"/>
            </div>
        </div>
        <!--end::Form-->
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
    </x-data-table.actions>
    <x-general.search-table
            title="Receipt"
    ></x-general.search-table>
@endsection
@section('content')
    {{-- Receipts --}}
    <div class="card card-flush">
        <div class="card-header">
            <div class="card-title align-items-start flex-column">
                <div class="card-label fw-bold fs-3 mb-1">
                    Receipts
                </div>
                <span class="text-muted fw-semibold fs-7">
                    <span id="receiptCounter">0</span> Generated Receipts
                </span>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table
                table-id="salesTable">
                <th>SON</th>
                <th>Terminal</th>
                <th>Total</th>
                <th>Sold By</th>
                <th>Store</th>
                <th>Time</th>
                <th></th>
            </x-data-table.table>
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

            // Table Options
            $options = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {'data': 'son'},
                    {'data': 'pos.name'},
                    {'data': "total"},
                    {'data': 'sold_by.name'},
                    {'data': 'store.name'},
                    {'data': "created_at"},
                    {'data': "actions"},
                ],
                columnDefs: [
                    // Time
                    {
                        targets: -2,
                        render: function(data, type, full){
                            return `<small>${moment(full.created_at).format('MMM-DD-YYYY (hh:mm A)')}</small>`;
                        }
                    },
                    // Sales Type + payment method (payment badge only on sales — refunds are always cash)
                    {
                        targets: 0,
                        render: function(data, type, full){
                            var types = {
                                0: {title: 'Sales', class: 'badge badge-light-primary'},
                                1: {title: 'Refund', class: 'badge badge-light-danger'}
                            };
                            var paymentTypes = {
                                1: {title: 'Cash',     class: 'badge badge-light-success'},
                                2: {title: 'E-Wallet', class: 'badge badge-light-info'},
                                3: {title: 'Credit',   class: 'badge badge-light-warning'},
                                4: {title: 'Bank Transfer', class: 'badge badge-light-info'},
                                5: {title: 'Cheque',   class: 'badge badge-light-warning'},
                                6: {title: 'Card',     class: 'badge badge-light-info'},
                                7: {title: 'Gift Cert', class: 'badge badge-light-success'},
                                8: {title: 'Split',    class: 'badge badge-light-primary'}
                            };
                            var typeBadge = `<span class="${types[full.type].class}">${types[full.type].title}</span>`;
                            var paymentBadge = '';
                            if (full.type === 0 && paymentTypes[full.payment_type]) {
                                var p = paymentTypes[full.payment_type];
                                paymentBadge = ` <span class="${p.class}">${p.title}</span>`;
                            }
                            return `${full.son} ${typeBadge}${paymentBadge}`;
                        }
                    },
                    // POS
                    {
                        targets: 1,
                        render: function(data, type, full){
                            return (full.pos) ? full.pos.name : '';
                        }
                    },
                    {
                        targets: 2,
                        render: function(data, type, full){
                            return numberWithCommas(full.total.toFixed(2));
                        }
                    },
                    // Store
                    {
                        targets: 4,
                        render: function(data, type, full){
                            return (full.store) ? full.store.name : '';
                        }
                    }
                ],
                ajax: {
                    type: 'get',
                    url: '{{ route("report.receipts.data") }}',
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                    },
                    dataSrc: function(response){
                        $("#receiptCounter").text(numberWithCommas(response.table.original.data.length))
                        return response.table.original.data;
                    }
                }
            }
            var table = $("#salesTable");
            let dataTable = table.DataTable($options);
            const documentTitle = 'Receipts Report';
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
