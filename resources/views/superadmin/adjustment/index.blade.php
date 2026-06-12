@extends('superadmin.layouts.master')

@section('title')
    Manual Adjustment
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item active">Manual Adjustment</li>
@endsection

@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .select2-container--default .select2-selection--multiple {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.375rem 0.5rem;
            min-height: 42px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary);
        }
    </style>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="page-title">Manual Adjustment</h1>
        <p class="page-subtitle">Adjust receipt values for selected terminals</p>
    </div>

    <div class="card" style="border-left: 4px solid var(--danger);">
        <div class="card-body">
            <div class="row" style="margin-bottom: 1rem;">
                <div class="col-4">
                    <div class="form-group mb-0">
                        <label class="form-label">Date Range</label>
                        <input type="text" class="form-control" id="reservation" placeholder="Select date range">
                    </div>
                </div>
                <div class="col-8">
                    <div class="form-group mb-0">
                        <label class="form-label">Select Terminals</label>
                        <select name="posSelect[]" id="posSelect" class="form-control" multiple="multiple">
                            <option></option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="table" id="table">
                    <thead>
                        <tr>
                            <th>SON</th>
                            <th>Total</th>
                            <th>VATable Sales</th>
                            <th>VAT Amount</th>
                            <th>Non-VAT</th>
                            <th>VAT Exempt</th>
                            <th>Excess VATable</th>
                            <th>Excess VAT</th>
                            <th>Excess Non VAT</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Adjustment Settings</h3>
            <button type="button" id="btnAdjust" class="btn btn-danger btn-sm">
                <i class="fas fa-calculator"></i> Apply Adjustment
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">VAT Deduction %</label>
                        <input type="number" class="form-control" name="vat_rate" min="1" placeholder="Enter percentage">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">VAT Exempt Deduction %</label>
                        <input type="number" class="form-control" name="non_vat_rate" min="1" placeholder="Enter percentage">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Zero Rated Deduction %</label>
                        <input type="number" class="form-control" name="zero_rated_rate" min="1" placeholder="Enter percentage">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            var table = $("#table");
            let posSelect = $("#posSelect");
            $start = '{{ Carbon\Carbon::today()->format("Y-m-d") }}';
            $end = '{{ Carbon\Carbon::today()->format("Y-m-d") }}';
            let btnAdjust = $("#btnAdjust");

            $options = {
                serverside: true,
                processing: true,
                lengthChange: false,
                autoWidth: false,
                responsive: true,
                columns: [
                    {data: 'son'},
                    {data: 'total'},
                    {data: 'vatable'},
                    {data: 'vat'},
                    {data: 'non_vat'},
                    {data: 'zero_rated'},
                    {data: 'excess_vatable'},
                    {data: 'excess_vat'},
                    {data: 'excess_non_vat'},
                ],
                columnDefs: [
                    {
                        targets: 4,
                        render: function (data, type, full) {
                            return (full.non_vat + full.vat_exempt).toFixed(2)
                        }
                    }
                ],
                ajax: {
                    data: {
                        'startDate': function () { return $start },
                        'endDate': function () { return $end },
                    },
                    dataSrc: function (response) {
                        return response.data;
                    },
                    url: "{{ route('superadmin.adjustment.receipts') }}"
                }
            };

            posSelect.select2({
                placeholder: "Select POS / Terminal to Adjust",
                ajax: {
                    url: '{{ route('superadmin.settings.pos.select') }}',
                    delay: 250,
                    type: "get",
                    dataType: 'json',
                    data: function (params) {
                        return { search: params.term };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true
                }
            });

            let daterangePicker = $('#reservation');
            daterangePicker.daterangepicker({
                opens: 'left',
                startDate: moment($start),
                endDate: moment($end),
            }, function (start, end, label) {
                $start = start.format("YYYY-MM-DD");
                $end = end.format("YYYY-MM-DD");
            });

            btnAdjust.on("click", function () {
                let vat_rate = $("input[name='vat_rate']").val(),
                    non_vat_rate = $("input[name='non_vat_rate']").val(),
                    zero_rated_rate = $("input[name='zero_rated_rate']").val();
                let selectedPos = posSelect.val();

                $.ajax({
                    type: "POST",
                    data: {
                        'startDate': function () { return $start },
                        'endDate': function () { return $end },
                        'selectedPos': selectedPos,
                        'vr': vat_rate,
                        'nvr': non_vat_rate,
                        'zrr': zero_rated_rate,
                    },
                    url: "{{ route('superadmin.adjustment.receipts.adjust2') }}",
                    success: function (response) {
                        alert(response)
                    },
                    error: function (response) {
                        console.log(response);
                    }
                });
            });
        });
    </script>
@endsection
