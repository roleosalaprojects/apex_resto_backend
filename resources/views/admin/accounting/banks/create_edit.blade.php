<div class="modal fade" tabindex="-1" id="bankModal">
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
            <form action="#" method="" class="form w-100" novalidate="novalidate" id="bankForm" >
                @csrf
                <div class="modal-body">
                    <div class="mb-6 form-group fv-row">
                        {!! Form::label("bank_name", "Bank Name", ["class" => "form-label required"]) !!}
                        {!! Form::text("bank_name", "", ["class"=>"form-control", "required"]) !!}
                    </div>
                    <div class="mb-6 form-group fv-row">
                        {!! Form::label("account_name", "Account Name", ["class" => "form-label required"]) !!}
                        {!! Form::text("account_name", "", ["class"=>"form-control", "required"]) !!}
                    </div>
                    <div class="mb-6 form-group fv-row">
                        {!! Form::label("account_number", "Account Number", ["class" => "form-label required"]) !!}
                        {!! Form::text("account_number", "", ["class"=>"form-control", "required"]) !!}
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Account Type</label>
                        <select name="account_type" id="account_type" class="form-select">
                            <option></option>
                            <option value="0">Debit / Savings</option>
                            <option value="1">Checking</option>
                            <option value="2">Credit</option>
                            <option value="3">Passbook</option>
                            <option value="4">E-Wallet</option>
                        </select>
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label required">Starting Balance</label>
                        <input type="number" name="starting_balance" id="starting_balance" class="form-control" min="0">
                    </div>
                    <div class="mb-6 form-group fv-row">
                        <label for="" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" data-kt-autosize="true"></textarea>
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
