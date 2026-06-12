@extends('admin.layouts.master')
@section('title')
    Non-VAT Reports
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Non-VAT Report</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-5 col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Non-VAT Quarterly Selection
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="">Select Year</label>
                        <select class="form-control" id="year" name="param">
                            @foreach ($years as $year)
                                @if ($year->year != 0)
                                    <option>{{$year->year}}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quarter">Select Quarter</label>
                        <select name="quarter" id="quarter" class="form-control">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end">
                        <div class="form-group">
                            <button class="btn btn-info" id="submit">
                                Generate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-7 col-lg-8">

        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header border-0">
                    <h4>
                        Quarter Number: <strong><span id="quarter_num"
                                                      class="badge badge-danger">{{Carbon\Carbon::today()->quarter}}</span></strong>
                    </h4>
                </div>
                <div class="card-body">
                    <table class="table table-hover" id="tblQuarter">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sales</th>
                            <th>Refunds</th>
                            <th>Non-VAT Sales</th>
                            <th>Non-VAT Refunds</th>
                            <th>Non-VAT Payable</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script>
        $year = "{{Carbon\Carbon::today()->format('Y')}}";
        $quarter = "{{Carbon\Carbon::today()->quarter}}";

        var table = $("#tblQuarter");

        $options = {
            serverside: true,
            processing: true,
            lengthChange: false,
            autoWidth: false,
            responsive: true,
            columns: [
                {'data': 'y'},
                {'data': 'sales'},
                {'data': 'refunds'},
                {'data': 'snon_vat'},
                {'data': 'rnon_vat'},
                {'data': "non_vat_net"},
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function (data, type, full) {
                        return moment(Date.parse(full.y)).format("MMMM, YYYY");
                    }
                },
                {
                    targets: 6,
                    render: function (data, type, full) {
                        return '<a href="non_vat/' + full.y + '/' + {{auth()->user()->user_id}} + '" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i></a>';
                    }
                },
            ],
            ajax: {
                data: {
                    'q': function () {
                        return $quarter
                    },
                    'year': function () {
                        return $year
                    },
                    'user': {{auth()->user()->user_id}}
                },
                dataSrc: function (response) {
                    return response.data
                },
                url: "{{route('bir.non-vat')}}"
            }
        }
        $(function () {
            table.DataTable($options);
            $("#submit").on("click", function () {
                $year = $("#year").val();
                $quarter = $("#quarter").val();
                $("#quarter_num").text($quarter);
                $("#tblQuarter").DataTable().ajax.reload();
            });
        });

    </script>
@endsection
