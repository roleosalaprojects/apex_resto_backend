@extends('layout.app')
@section('header')
    - BIR Senior Citizen Report
@endsection
@section('title')
    BIR Senior Citizen Report
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('reports.bir.specialDiscounts.senior.index')}}">Senior Citizen Report Book</a></li>
    <li class="breadcrumb-item text-muted">POS {{$pos->name}} #{{$pos->number}}</li>
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
    <x-general.search-table title="Search Table"></x-general.search-table>
@endsection
@section('content')
    <div class="card mb-10">
        <div class="card-body">
            <div>
                <div class="d-flex justify-content-center">
                    <span class="fs-3 fw-semibold">
                        {{$pos->owner->name}}
                    </span>
                </div>
                <div class="d-flex justify-content-center">
                    <span class="fs-3 fw-semibold">
                        {{$pos->store->header}}
                    </span>
                </div>
                <div class="d-flex justify-content-center">
                    <span class="fs-3 fw-semibold">
                        {{$pos->store->tin}}
                    </span>
                </div>
            </div>
            <div>
                <div>
                    <span class="fs-4 fw-semibold">
                        {{env('APP_NAME')}}&nbspV{{env('APP_VERSION')}}
                    </span>
                </div>
                <div>
                    <span class="fs-4 fw-semibold">
                        Serial: {{$pos->serial ? $pos->serial : 'N/A'}}
                    </span>
                </div>
                <div>
                    <span class="fs-4 fw-semibold">
                        MIN: {{$pos->min ? $pos->min : 'N/A'}}
                    </span>
                </div>
                <div>
                    <span class="fs-4 fw-semibold">
                        Terminal #: {{$pos->number}}
                    </span>
                </div>
                <div>
                    <span class="fs-4 fw-semibold">
                        Generation Date: {{\Carbon\Carbon::now()->format('F d, Y')}}
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <x-data-table.table
                        table-id="storesTable"
                >
                    <th>Date</th>
                    <th>SC Name</th>
                    <th>SC ID No.</th>
                    <th>SC TIN</th>
                    <th>SI/OR #</th>
                    <th>Sales (Incl. VAT)</th>
                    <th>VAT Amount</th>
                    <th>VAT Exempt Sales</th>
                    <th>Discount</th>
                    <th>Net Sales</th>
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
            $option = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                order: [[4, 'asc']],
                columns: [
                    {data: 'created_at'},
                    {data: 'special_discount_name'},
                    {data: 'special_discount_id'},
                    {data: 'special_discount_tin'},
                    {data: 'son'},
                    {data: 'total'},
                    {data: 'vat_special_discounts'},
                    {data: 'vat_exempt'},
                    {data: 'sc_discount'},
                    {data: 'total'},
                ],
                columnDefs: [
                    {
                        targets: 0,
                        render: function (data, type, row) {
                            return moment(data).format('DD-MM-YYYY hh:mm:ss a');
                        }
                    },
                    {
                        targets: 5,
                        render: function (data, type, full) {
                            return numberWithCommas((full.vat_special_discounts + full.vat_exempt + full.sc_discount).toFixed(2));
                        }
                    }
                ],
                ajax: {
                    type: "GET",
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                        'pos_id': {{$pos->id}}
                    },
                    dataSrc: function (response){
                        return response.data.original.data
                    },
                    url: '{{ route('reports.bir.specialDiscounts.senior.table') }}'
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
            table.on('click', '.btn-active-color-danger', function(e){
                var id = $(this).val();
                var name = $("#name_"+id).val();
                $('#store_name').html(name);
                $('#confirm_delete').attr('form','form_delete_'+id)
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
