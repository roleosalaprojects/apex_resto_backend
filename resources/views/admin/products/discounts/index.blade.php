@extends('layout.app')
@section('header')
    - Discounts
@endsection
@section('title')
    Discounts
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Discounts</li>
@endsection
@section('actions')
    @if ($access->itms_create)
        <a href="{{route('discounts.create')}}" class="btn btn-success btn-sm">Create</a>
    @endif
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
    <div id="datatables_menu" class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4" data-kt-menu="true" id="card_actions" style="">
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
    <!--end::Menu 1-->
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="tblDiscounts">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Rate</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal for Deletion --}}
    <div class="modal fade" tabindex="-1" id="deleteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Delete Discount</h5>

                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                    <!--end::Close-->
                </div>

                <div class="modal-body">
                    <h5 id="category_name">Name Here</h5>
                    <label class="form-label">Are you sure you want to delete this Discount?</label>
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
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
<script>
    $(document).ready(function(){
        let table = $("#tblDiscounts");
        $options = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            columns: [
                {data: 'name'},
                {data: 'rate'},
                {data: 'actions'},
            ],
            columnDefs: [
                {
                    targets: -1,
                    orderable: false,
                },
                {
                    targets: 1,
                    orderable: true,
                    width: 170,
                    render: function(data, type, full){
                        return full.rate + " %";
                    }
                }
            ],
            ajax: {
                dataSrc: function (response){
                    console.log(response.data);
                    return response.data
                },
                url: '{{ route('discounts.table') }}'
            },
        }
        let dataTable = table.DataTable($options);

        const documentTitle = 'Discounts Listing';
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

        table.on('click', '.btn-active-color-danger', function(e){
            var id = $(this).val();
            var name = $("#name_"+id).val();
            $('#customer_name').html(name);
            $('#confirm_delete').attr('form','form_delete_'+id)
        });
        $('#tableSearch').keyup(function(){
            table.DataTable().search($(this).val()).draw();
        });
    });
</script>
@endsection
