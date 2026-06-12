<div class="modal fade" tabindex="-1" id="delete{{ $titleIdentifier }}Modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Delete <span id="deleteModalTitle">{{ $titleIdentifier }}</span></h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <form action="#" id="delete{{ $titleIdentifier }}Form" method="DELETE" class="form w-100" novalidate="novalidate">
                    <input type="hidden" name="{{ $identifier }}_id" id="{{ $identifier }}_id">
                    <div class="form-group fv-row">
                        <label for="delete_name" class="form-label required">{{ $titleIdentifier }} Name</label>
                        <input type="text" name="delete_name" id="delete_name" class="form-control" disabled/>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" id="btnDelete{{ $titleIdentifier }}" class="btn btn-danger">
                    <span class="indicator-label">Delete</span>
                    <span class="indicator-progress">Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>
