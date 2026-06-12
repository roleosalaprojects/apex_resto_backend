@extends('layout.app')
@section('title')
    Cash Receipt Journal
@endsection
@section('actions')
    <div class="d-none d-lg-block w-100 mb-5 mb-lg-0 position-relative">
        <!--begin::Hidden input(Added to disable form autocomplete)-->
        <!--end::Hidden input-->
        <!--begin::Icon-->
        <!--begin::Svg Icon | path: icons/duotune/general/gen021.svg-->
        <span class="svg-icon svg-icon-2 svg-icon-gray-700 position-absolute top-50 translate-middle-y ms-4">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="currentColor"></rect>
                    <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="currentColor"></path>
                </svg>
            </span>
        <!--end::Svg Icon-->
        <!--end::Icon-->
        <!--begin::Input-->
        <input type="text" id="tableSearch" class="form-control form-control h-40px bg-body ps-13 fs-7" placeholder="Search..." autocomplete="off">
        <!--end::Input-->
    </div>
    <!--begin::Menu-->
    <button type="button" class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
        <!--begin::Svg Icon | path: icons/duotune/general/gen024.svg-->
        <span class="svg-icon svg-icon-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24">
                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                    <rect x="5" y="5" width="5" height="5" rx="1" fill="currentColor"></rect>
                    <rect x="14" y="5" width="5" height="5" rx="1" fill="currentColor" opacity="0.3"></rect>
                    <rect x="5" y="14" width="5" height="5" rx="1" fill="currentColor" opacity="0.3"></rect>
                    <rect x="14" y="14" width="5" height="5" rx="1" fill="currentColor" opacity="0.3"></rect>
                </g>
            </svg>
        </span>
        <!--end::Svg Icon-->
    </button>
    <!--begin::Menu 1-->
    <div class="menu menu-sub menu-sub-dropdown w-250px w-md-300px menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4" data-kt-menu="true" id="datatables_menu" style="">
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
        <!--begin::Header-->
        <div class="px-5 py-3">
            <div class="fs-5 text-dark fw-bold">Export Options</div>
        </div>
        <!--end::Header-->
        <!--begin::Menu item-->
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3" data-kt-export="copy">
                Copy to clipboard
            </a>
        </div>
        <!--end::Menu item-->
        <!--begin::Menu item-->
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3" data-kt-export="excel">
                Export as Excel
            </a>
        </div>
        <!--end::Menu item-->
        <!--begin::Menu item-->
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3" data-kt-export="csv">
                Export as CSV
            </a>
        </div>
        <!--end::Menu item-->
        <!--begin::Menu item-->
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3" data-kt-export="pdf">
                Export as PDF
            </a>
        </div>
        <!--end::Menu item-->
        <!--begin::Hide default export buttons-->
        <div id="datatable_buttons" class="d-none"></div>
        <!--end::Hide default export buttons-->
    </div>
@endsection
@section('content')
    <div class="card card-flush">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-bordered" id="table">
                    <thead>
                        <tr class="fw-bold fs-6 text-gray-800">
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Particulars</th>
                            <th>OR #</th>
                            <th>Cash (Dr)</th>
                            <th>Sales (Cr)</th>
                            <th>Output Tax (Cr)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    {{-- DataTables --}}
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function(){
            $startDate = moment().startOf('day');
            $endDate = moment().endOf('day');
            // Select2
            var store_select = $("#store_select");

            // Init Select2
            store_select.select2({
                dropdownParent: $("#datatables_menu"),
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
            var tableOptions = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {data: 'created_at'},
                    {data: 'customer'},
                    {data: 'lines'},
                    {data: 'son'},
                    {data: 'total'},
                    {data: 'vatable'},
                    {data: 'vat'},
                ],
                columnDefs: [
                    {
                        targets: 0,
                        render: function(data, type, full){
                            return moment(full.created_at).format('YYYY-MM-DD hh:mm A')
                        }
                    },
                    {
                        targets: 1,
                        render: function(data, type, full){
                            return full.customer ? full.customer.name : 'WALK-IN';
                        }
                    },
                    {
                        targets: 2,
                        render: function(data, type, full){
                            let response = '';
                            full.lines.forEach(line => {
                                response += line.qty + ' - ' + line.item.name + '<br />';
                            });
                            return response
                        }
                    },
                ],
                ajax: {
                    type: 'get',
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                    },
                    url: "{{ route('tests.cash_receipt_journal.data') }}",
                    dataSrc: function(response){
                        console.log(response);
                        return response.original.data;
                    }

                }
            }

            var table = $("#table");
            table.DataTable(tableOptions);
            const documentTitle = 'Cash Receipt Journal';
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
                table.DataTable().ajax.reload();
            });

            $('#tableSearch').keyup(function(){
                table.DataTable().search($(this).val()).draw();
            });

            store_select.on('select2:select', function(){
                table.DataTable().ajax.reload()
            })

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
        });
    </script>
@endsection
