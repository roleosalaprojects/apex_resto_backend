@extends('superadmin.layouts.master')

@section('title')
    Receipt Normalization
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item active">Normalize Receipts</li>
@endsection

@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
@endsection

@section('content')
    <div class="page-header">
        <h1 class="page-title">Receipt Normalization</h1>
        <p class="page-subtitle">Normalize receipt values by date range</p>
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
            <h3 class="card-title">Normalization</h3>
            <button type="button" id="btnAdjust" class="btn btn-danger btn-sm">
                <i class="fas fa-sync"></i> Normalize Receipts
            </button>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(document).ready(function () {
            var table = $("#table");
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
                $.ajax({
                    type: "POST",
                    data: {
                        'startDate': function () { return $start },
                        'endDate': function () { return $end },
                    },
                    url: "{{ route('superadmin.adjustment.receipts.normalize') }}",
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
