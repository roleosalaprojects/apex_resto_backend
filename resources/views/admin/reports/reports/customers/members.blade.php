@extends('layout.app')
@section('header')
    - Members Report
@endsection
@section('title')
    Members Report
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Members Report</li>
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
        title="Members"
    ></x-general.search-table>
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-body">
                    <table-responsive>
                        <table class="table table-hover table-rounded border border-2 table-row-bordered gy-5 gs-7" id="table">
                            <thead>
                                <tr class="fw-semibold fs-6 text-gray-800">
                                    <th>Member Name</th>
                                    <th>Receipts</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </table-responsive>
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

            let groupColumn = 0;
            let tableOptions = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {'data': 'customer.name'},
                    {'data': 'son'},
                    {'data': 'created_at'},
                    {'data': 'total'},
                ],
                "order": [
                    [groupColumn, "asc"]
                ],
                columnDefs: [
                    {
                        targets: 1,
                        render: function(data, type, full){
                            return `<span class="fw-bold fs-7">${full.son} </span>`;
                        }
                    },
                    {
                        targets: 2,
                        render: function(data, type, full){
                            return `<span class="fw-bold fs-7">${moment(full.created_at).format('MMMM DD YY, h:mm:ss a')} </span>`;
                        }
                    },
                    {
                        targets: 3,
                        render: function(data, type, full){
                            return `<span class="fw-bold fs-7">${numberWithCommas(full.total)} </span>`;
                        }
                    },
                    {
                        "visible": false,
                        "targets": groupColumn
                    }
                ],
                "drawCallback": function(settings) {
                    var api = this.api();
                    var rows = api.rows({
                        page: "current"
                    }).nodes();
                    var last = null;

                    api.column(groupColumn, {
                        page: "current"
                    }).data().each(function(group, i) {
                        if (last !== group) {
                            $(rows).eq(i).before(
                                "<tr class=\"group fs-6 fw-bolder\"><td colspan=\"5\">" + group + "</td></tr>"
                            );

                            last = group;
                        }
                    });
                },
                ajax: {
                    type: 'get',
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                    },
                    url: '{{ route('customers.members.report.table') }}',
                    dataSrc: function(response){
                        return response.data;
                    }
                }
            }

            // Order by the grouping
            $("#table tbody").on("click", "tr.group", function() {
                var currentOrder = table.order()[0];
                if (currentOrder[0] === groupColumn && currentOrder[1] === "asc") {
                    table.order([groupColumn, "desc"]).draw();
                } else {
                    table.order([groupColumn, "asc"]).draw();
                }
            });

            var table = $("#table");
            let dataTable = table.DataTable(tableOptions);
            let documentTitle = `Members Report `;
            var buttons = new $.fn.dataTable.Buttons(table, {
                buttons: [
                    {
                        extend: 'copyHtml5',
                        title: function() { return documentTitle + ' from ' + moment($startDate).format('MMMM Do YYYY') + ' to ' + moment($endDate).format('MMMM Do YYYY')}
                    },
                    {
                        extend: 'excelHtml5',
                        title: function() { return documentTitle + ' from ' + moment($startDate).format('MMMM Do YYYY') + ' to ' + moment($endDate).format('MMMM Do YYYY')}
                    },
                    {
                        extend: 'csvHtml5',
                        title: function() { return documentTitle + ' from ' + moment($startDate).format('MMMM Do YYYY') + ' to ' + moment($endDate).format('MMMM Do YYYY')}
                    },
                    {
                        extend: 'pdfHtml5',
                        title: function() { return documentTitle + ' from ' + moment($startDate).format('MMMM Do YYYY') + ' to ' + moment($endDate).format('MMMM Do YYYY')}
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
        })
    </script>
@endsection
