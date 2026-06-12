@extends('layout.app')
@section('header')
    - Stock Adjustments
@endsection
@section('title')
    Stock Adjustments
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Stock Adjustments</li>
@endsection
@section('actions')
    <x-data-table.actions>
    </x-data-table.actions>
    @if ($access->adjstmnts_create)
        <a href="{{route('adjustments.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <x-data-table.table
                table-id="tblAdjustments"
            >
                <th>SO#</th>
                <th>Store</th>
                <th>Created by</th>
                <th>Confirmed by</th>
                <th>Status</th>
                <th>Creation Date</th>
                <th></th>
            </x-data-table.table>
        </div>
    </div>
{{-- Modal for Deletion --}}
<div class="modal fade" tabindex="-1" id="deleteModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Delete Stock Adjustment Order</h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <h5 id="adjustment_name"></h5>
                <label class="form-label">Are you sure you want to delete this Stock Adjustment Order?</label>
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
            {'data': 'so'},
            {'data': 'store.name'},
            {'data': 'creator.name'},
            {'data': 'receiver'},
            {'data': 'status'},
            {'data': 'created_at'},
            {'data': 'actions'}
        ],
        columnDefs: [
            {
                targets: 3,
                render: function(data, type, full){
                    return (full.receiver) ? full.receiver.name : ''
                }
            },
            {
                targets: -2,
                render: function(data, type, full){
                    // return  `<small>${moment(full.created_at).format('MMM-DD-YYYY (hh:mm A)')}</small>`
                    return full.created_at;
                }
            },
            {
                targets: -3,
                render: function(data, type, full){
                    var status = {
                        2: {'title': 'Pending', 'class': ' text-warning'},
                        1: {'title': 'Approved', 'class': 'text-success'},
                    };
                    return '<span class="' + status[full.status].class + '">' + status[full.status].title + '</span>';
                }
            }
        ],
        ajax: {
            data:{
                'user':{{auth()->user()->user_id}}
            },
            url: "{{route('adjustments.table')}}",
            dataSrc: function(response){
                return response.data;
            }
        }
      };
        $(function() {
        var table = $("#tblAdjustments").on('click', '.btn-active-color-danger', function(e){
            var id = $(this).val();
            var name = $("#name_"+id).val();
            $('#adjustment_name').html(name);
            $('#confirm_delete').attr('form','form_delete_'+id)
        });

        let dataTable = table.DataTable($options);
            const documentTitle = 'Stock Adjustments Records';
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
      });

    </script>
@endsection
