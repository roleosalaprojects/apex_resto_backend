<div class="modal fade" tabindex="-1" id="deleteCashModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Delete Cash Account</h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="delete_name" id="delete_name" class="form-label required">Cash Name</label>
                    <input type="text" name="delete_name" id="delete_name" class="form-control" disabled>
                    <span class="text-danger d-none" id="delete_message">

                    </span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" id="btnDeleteCash" class="btn btn-info">
                    <span class="indicator-label">Continue</span>
                    <span class="indicator-progress">Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>
