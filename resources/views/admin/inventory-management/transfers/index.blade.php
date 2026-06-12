@extends('layout.app')
@section('header')
    - Transfer Orders
@endsection
@section('title')
    Transfer Orders
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Transfer Orders</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table title="Transfer Order"></x-general.search-table>
    @if ($access->trnsfrs_create)
        <a href="{{route('transfers.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-body">
                    <div class="text-justify">
                        <div class="form-group mb-5">
                            <label class="form-label">Select Date</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                <i class="far fa-calendar-alt"></i>
                                </span>
                                <input type="text" class="form-control float-right" id="daterangepicker" name="daterangepicker">
                            </div>
                            <!-- /.input group -->
                        </div>
                    </div>
                    <x-data-table.table
                        table-id="tblTransfers"
                    >
                        <th>TO #</th>
                        <th>Source Store</th>
                        <th>Destination Store</th>
                        <th>Received</th>
                        <th>Created by</th>
                        <th>Status</th>
                        <th>Creation Date</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
{{-- Modal for Deletion --}}
<div class="modal fade" tabindex="-1" id="deleteModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Delete Transfer Order</h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <h5 id="transfer_name">Name Here</h5>
                <label class="form-label">Are you sure you want to delete this Transfer Order?</label>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="confirm_delete" class="btn btn-danger font-weight-bold" form="">Delete</button>
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
        $options = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            buttons: [
                'print',
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5',
            ],
            columns: [
                {'data': 'to'},
                {'data': 'source.name'},
                {'data': 'destination.name'},
                {'data': 'received'},
                {'data': 'creator.name'},
                {'data': 'status'},
                {'data': 'created_at'},
                {'data': 'actions'},
            ],
            columnDefs: [
                {
                    targets: 3,
                    render: function(data, type, full){
                        return `${full.received} of ${full.total}`;
                    }
                },
                {
                    targets: -2,
                    orderable: false,
                    render: function(data, type, full){
                        return  `<small>${moment(full.created_at).format('MMM-DD-YYYY (hh:mm A)')}</small>`;
                    }
                },
                {
                    targets: 5,
                    render: function(data, type, full){
                        var status = {
                            2: {'title': 'Pending', 'class': 'text-info'},
                            1: {'title': 'Received', 'class': 'text-success'},
                        };
                        return '<span class="' + status[full.status].class + '">' + status[full.status].title + '</span>';
                    }
                }
            ],
            ajax: {
                data:{
                    'startDate': function() { return $startDate},
                    'endDate': function(){return $endDate},
                },
                dataSrc: function(response){
                    return response.data;
                },
                url: "{{ route('transfers.table') }}"
            }
        };
        $("#tblTransfers").on('click', '.btn-active-color-danger', function(e){
            var id = $(this).val();
            var name = $("#name_"+id).val();
            $('#transfer_name').html("TO #:" + name);
            $('#confirm_delete').attr('form','form_delete_'+id)
        });
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
        }, function(start, end, label) {
            $startDate = start.format("YYYY-MM-DD");
            $endDate = end.format("YYYY-MM-DD");
            table.DataTable().ajax.reload();
        });
        function drawDataTable(){
            $('#tblTransfers').DataTable().columns.adjust().draw();
        }
        var table = $("#tblTransfers");
        let dataTable = table.DataTable($options);
        const documentTitle = 'Transfer Orders Records';
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
    })
</script>
@endsection
