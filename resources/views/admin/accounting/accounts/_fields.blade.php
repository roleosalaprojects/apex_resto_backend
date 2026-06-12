<div class="modal fade" tabindex="-1" id="accountModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title"><span id="titleModal"></span>Account</h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>
            <form action="#" method="#" class="form w-100" novalidate="novalidate" id="accountForm" >
                @csrf
                <div class="modal-body">
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Account Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Description</label>
                        <textarea name="description" id="description" class="form-control" data-kt-autosize="true"></textarea>
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Starting Balance</label>
                        <input type="number" name="starting_balance" id="starting_balance" class="form-control" min="0">
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Current Balance</label>
                        <input type="number" name="current_balance" id="current_balance" class="form-control" min="0">
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label">Account Type</label>
                        <select name="type" id="type" class="form-select">
                            <option></option>
                            <option value="1">Asset</option>
                            <option value="2">Liability</option>
                            <option value="3">Owner's Equity</option>
                            <option value="4">Revenue</option>
                            <option value="5">Expenses</option>
                        </select>
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Account Number</label>
                        <input type="number" name="number" id="number" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="btnSubmit" class="btn btn-success">
                        <span class="indicator-label">Continue</span>
                        <span class="indicator-progress">Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
