@extends('layout.app')
@section('header')
    - Edit Transfer Order
@endsection
@section('title')
    Edit Transfer Order: {{$transfer->to}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('transfers.index')}}">Transfer Orders</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('transfers.show', $transfer->id)}}">TO
            #: {{$transfer->to}}</a></li>
    <li class="breadcrumb-item text-muted">Edit TO #: {{$transfer->to}}</li>
@endsection
@section('actions')
    <button type="button" class="btn btn-sm btn-info" id="btnSubmit">Update</button>
@endsection
@section('content')
    {!! Form::open(['route'=>["transfers.update", $transfer->id], 'method'=>"PUT", "id"=>"editTransfer"]) !!}
    <div class="row">
        @include('admin.inventory-management.transfers._fields')
    </div>
    {!! Form::close() !!}
@endsection
@section('add-scripts')
    <script src="{{ asset('assets/js/swal.js') }}"></script>
    <script>
        $(document).ready(function () {
            let submitButton, form, validator;

            form = document.querySelector("#editTransfer");
            submitButton = $("#btnSubmit");

            validator = FormValidation.formValidation(
                form,
                {
                    fields: {
                        'source_store': {
                            validators: {
                                notEmpty: {
                                    message: "Source location selection is required.",
                                }
                            }
                        },
                        'destination_store': {
                            validators: {
                                notEmpty: {
                                    message: "Destination location selection is required.",
                                }
                            }
                        }
                    }
                }
            )

            // Private functions
            let checker = arr => arr.every(v => v === true);
            submitButton.on('click', function (e) {
                e.preventDefault();
                var items = $("input[name='qty[]']").map(function () {
                    return $(this).val()
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
                validator.validate().then(function (status) {
                    console.log(items);
                    if (items.length > 0) {
                        if (status == 'Valid' && checker(qty)) {
                            form.submit();
                        } else {
                            toastr.error('There are missing input fields. Please check again.');
                        }
                    } else {
                        errorSwal('Item / Products missing.', 'Please enter at lease one(1) Item / Product first before proceeding.');
                    }
                })
            })
        })
    </script>
@endsection
