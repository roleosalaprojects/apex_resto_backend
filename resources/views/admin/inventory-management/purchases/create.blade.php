@extends('layout.app')
@section('header')
    - Create Purchase Order
@endsection
@section('title')
    Create Purchase Order
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('purchases.index')}}">Purchase Orders</a></li>
    <li class="breadcrumb-item text-muted">Create Purchase Order</li>
@endsection
@section('actions')
    {{-- {!! Form::submit("Create", ["class"=>"btn-sm btn btn-success", "form"=>"createPurchase"]) !!} --}}
    <button type="button" id="createButton" class="btn btn-success btn-sm">Create</button>
@endsection
@section('content')
    {!! Form::open(['route'=>'purchases.store', 'id'=>'createPurchase']) !!}
    <div class="row">
        @include('admin.inventory-management.purchases._fields')
    </div>
    {!! Form::close() !!}
@endsection
@section('add-scripts')
    <script src="{{ asset('assets/js/swal.js') }}"></script>
    <script>
        $(document).ready(function () {
            let validator, form, submitButton;
            handleForm();

            // Private functions
            let checker = arr => arr.every(v => v === true);

            function handleForm() {
                form = document.querySelector("#createPurchase");
                submitButton = document.querySelector("#createButton");
                validator = FormValidation.formValidation(
                    form,
                    {
                        fields: {
                            'supplier': {
                                validators: {
                                    notEmpty: {
                                        message: "Supplier selection is required.",
                                    }
                                }
                            },
                            'store': {
                                validators: {
                                    notEmpty: {
                                        message: "Store selection is required.",
                                    }
                                }
                            },
                            'purchased': {
                                valdiators: {
                                    notEmpty: {
                                        message: "Purchase date is required."
                                    }
                                }
                            },
                            'expect': {
                                validators: {
                                    notEmpty: {
                                        message: "Expected date is required."
                                    }
                                }
                            }
                        },
                        plugins: {
                            trigger: new FormValidation.plugins.Trigger(),
                            bootstrap: new FormValidation.plugins.Bootstrap5({
                                rowSelector: '.fv-row',
                                eleInvalidClass: "",
                                eleValidClass: "",
                            })
                        }
                    },
                )

                submitButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    // Assign Variables to inputs
                    var items = $("input[name='item_id[]']").map(function () {
                        return $(this).val()
                    })
                    var item_id = $("input[name='item_id[]']").map(function () {
                        if (!$(this).val() || $(this).val() == null) {
                            $(this).addClass('is-invalid');
                            $(this).after("<span class='text-danger item-field'>Cannot be blank!</span>")
                            return false;
                        } else {
                            $(this).removeClass('is-invalid')
                            $(this).closest("tr").find(".item-field").remove();
                            return true;
                        }
                    }).get();
                    var qty = $("input[name='qty[]']").map(function () {
                        if (!$(this).val() || $(this).val() == null) {
                            $(this).addClass('is-invalid');
                            $(this).after("<span class='text-danger qty-field'>Cannot be blank!</span>")
                            return false;
                        } else {
                            $(this).removeClass('is-invalid')
                            $(this).closest("tr").find(".qty-field").remove();
                            return true;
                        }
                    }).get();
                    var cost = $("input[name='price[]']").map(function () {
                        if (!$(this).val() || $(this).val() == null) {
                            $(this).addClass('is-invalid');
                            $(this).after("<span class='text-danger price-field'>Cannot be blank!</span>")
                            return false;
                        } else {
                            $(this).removeClass('is-invalid')
                            $(this).closest("tr").find(".price-field").remove();
                            return true;
                        }
                    }).get();
                    var addDesc = $("input[name='addDescription[]']").map(function () {
                        if (!$(this).val() || $(this).val() == null) {
                            $(this).addClass('is-invalid');
                            $(this).after("<span class='text-danger description-field'>Cannot be blank!</span>")
                            return false;
                        } else {
                            $(this).removeClass('is-invalid')
                            $(this).closest("tr").find(".description-field").remove();
                            return true;
                        }
                    }).get();
                    var addAmount = $("input[name='addAmount[]']").map(function () {
                        if (!$(this).val() || $(this).val() == null) {
                            $(this).addClass('is-invalid');
                            $(this).after("<span class='text-danger amount-field'>Cannot be blank!</span>")
                            return false;
                        } else {
                            $(this).removeClass('is-invalid')
                            $(this).closest("tr").find(".amount-field").remove();
                            return true;
                        }
                    }).get();
                    validator.validate().then(function (status) {
                        if (items.length == 0) {
                            errorSwal('Invalid Items', 'Please select at least 1 item to proceed');
                        }
                        if (status == 'Valid' && checker(item_id) && checker(qty) && checker(cost) && items.length > 0) {
                            form.submit();
                        }
                    });
                })
            }
        })
    </script>
@endsection
