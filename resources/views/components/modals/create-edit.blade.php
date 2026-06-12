<div class="modal fade" tabindex="-1" id="{{ $identifier }}Modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <span id="{{ $identifier }}ModalType">Create</span>
                    {{ $title }}
                </h3>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <form action="#" id="{{ $identifier }}Form" method="POST" class="form w-100" novalidate="novalidate">
                    <input type="hidden" name="{{ $identifier }}_id" id="{{ $identifier }}_id">
                    {{ $slot }}
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" id="btnSubmitCreateEditForm" class="btn btn-primary">
                    <span class="indicator-label" id="{{ $identifier }}ButtonType">Create</span>
                    <span class="indicator-progress">Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
