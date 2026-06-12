@extends('layout.app')
@section('header')
    - Update Product
@endsection
@section('title')
    Update Product : {{$item->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('items.index')}}">Products Listing</a></li>
    <li class="breadcrumb-item  text-muted">Update item</li>
@endsection
@section('content')
    <form action="{{ route('items.update', ['item' => $item->id]) }}" id="editItem" method="POST"
          enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.products.items._fields')
    </form>
@endsection
@section('scripts')
    
    <script>
        $(document).ready(function () {
            var form, submitButton, validator, btnAddUnit, unitTable, categorySelect, unitSelect, supplierSelect,
                taxSelect;
            var btnDiscount = $("input[name=discountable]");
            // Constant Variables
            const selectedCategory = "{{ $item->category_id }}";
            const selectedSupplier = "{{ $item->supplier_id }}"
            const selectedTax = "{{ $item->text_id }}";

            assignVariables();
            select2Init();
            initSelect2Values();
            handleForm();

            // Event Handlers
            btnDiscount.on("click", function (e) {
                var dom = $("#specialDiscounts");
                ($(this).val() == 1) ? dom.removeClass("d-none") : dom.addClass("d-none");
            });
            unitTable.on("click", ".btn-danger", function (e) {
                e.stopPropagation();
                $(this).closest("tr").remove();
            });

            // Handle Add Unit
            btnAddUnit.on("click", function () {
                if (unitSelect.val()) {
                    $.ajax({
                        url: "/admin/units/get/" + unitSelect.val(),
                        type: "GET",
                        success: function (response) {
                            addToUnitTable(response);
                        },
                        error: function (response) {
                            errorSwal('Error', response.message);
                        }
                    });
                    unitSelect.val(null).trigger("change");
                } else {
                    errorSwal("Unit Selection", "Select a unit of measure first before adding.")
                }
            });


            // Functions
            let checker = arr => arr.every(v => v === true);

            function assignVariables() {
                form = document.querySelector("#editItem");
                submitButton = document.querySelector("#btnSubmit");
                // Table
                unitTable = $("#unitTable");
                // Select2 Elements
                categorySelect = $("#categorySelect");
                unitSelect = $("#unitSelect");
                supplierSelect = $("#supplierSelect");
                taxSelect = $("#taxSelect");
                btnAddUnit = $("#btnAddUnit");
            }

            // Public functions
            function select2Init() {
                categorySelect.select2({
                    ajax: {
                        url: '{{ route("categories.select") }}',
                        delay: 250,
                        type: "get",
                        dataType: 'json',
                        data: function (params) {
                            var query = {
                                search: params.term,
                            }
                            return query;
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });
                unitSelect.select2({
                    ajax: {
                        url: '/admin/units/select',
                        delay: 250,
                        type: "get",
                        dataType: 'json',
                        data: function (params) {
                            var query = {
                                search: params.term,
                            }
                            return query;
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });
                supplierSelect.select2({
                    ajax: {
                        url: '/admin/suppliers/select',
                        delay: 250,
                        type: "get",
                        dataType: 'json',
                        data: function (params) {
                            var query = {
                                search: params.term,
                            }
                            return query;
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });

            }

            function addToUnitTable(unit) {
                var canLock = !!(window.__itemUomRecalc && window.__itemUomRecalc.canManageUnitLock);
                var lockCell = canLock
                    ? `<td class="align-middle text-center">
                           <div class="form-check form-switch form-check-custom form-check-solid">
                               <input type="hidden" name="locked[]" value="0" data-locked-flag>
                               <input class="form-check-input" type="checkbox" data-locked-toggle>
                           </div>
                       </td>`
                    : '';
                var row = $(`<tr>
                    <td>
                        <input type="hidden" name="uom_id[]" value="${unit.id}"/>${unit.name}
                    </td>
                    <td>
                        <input class="form-control" name="qty[]" type="number" data-numeric-only required/>
                    </td>
                    <td>
                        <input type="number" name="price[]" class="form-control" value="0" min="0" data-numeric-only required/>
                    </td>
                    <td class="align-middle"><span class="js-cost-basis text-muted">—</span></td>
                    <td class="align-middle"><span class="js-margin fw-semibold">—</span></td>
                    <td>
                        <input type="text" name="uom_barcode[]" class="form-control" value=""/>
                    </td>
                    ${lockCell}
                    <td>${deleteButton()}</td>
                </tr>`);
                unitTable.append(row);
            }

            // Initialize Select2 Values
            function initSelect2Values() {
                // Supplier
                @if($item->supplier_id)
                $.ajax({
                    type: "get",
                    url: '{{ route('supplier.get', $item->supplier_id) }}',
                }).then(function (response) {
                    var data = response;
                    var option = new Option(data.name, data.id, true, true);
                    var select = supplierSelect;
                    select.append(option).trigger('change');
                    select.trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                })
                @endif
                // Category
                @if($item->category_id)
                $.ajax({
                    type: "get",
                    url: '{{ route('category.get', $item->category_id) }}',
                }).then(function (response) {
                    var data = response;
                    var option = new Option(data.name, data.id, true, true);
                    var select = categorySelect;
                    select.append(option).trigger('change');
                    select.trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                })
                @endif
                // Tax
                @if($item->tax_id)
                $.ajax({
                    type: "get",
                    url: '{{ route('tax.get', $item->tax_id ?? 0) }}',
                }).then(function (response) {
                    var data = response;
                    var option = new Option(data.name, data.id, true, true);
                    var select = taxSelect;
                    select.append(option).trigger('change');
                    select.trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                })
                @endif
            }

            function deleteButton() {
                return `<button type="button" class="btn btn-danger btn-icon">
                        <span class="svg-icon svg-icon-muted svg-icon-2hx">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                            </svg>
                        </span>
                        </button>`;
            }

            // Form Handler
            function handleForm() {
                // Validators
                validator = FormValidation.formValidation(
                    form,
                    {
                        fields: {
                            'name': {
                                validators: {
                                    notEmpty: {
                                        message: "Product name is required"
                                    }
                                }
                            },
                            'cost': {
                                validators: {
                                    notEmpty: {
                                        message: "Product cost is required."
                                    },
                                    numeric: {
                                        message: "Invalid number format.",
                                    }
                                }
                            },
                            'main_price': {
                                validators: {
                                    notEmpty: {
                                        message: "Product base price is required.",
                                    },
                                    numeric: {
                                        message: "Invalid number format.",
                                    }
                                }
                            },
                            'markup': {
                                validators: {
                                    notEmpty: {
                                        message: "Product markup is required.",
                                    },
                                    integer: {
                                        message: "Invalid number format.",
                                        decimalSeparator: "."
                                    }
                                }
                            },
                            'discounted': {
                                validators: {
                                    notEmpty: {
                                        message: "Product Discount selection is required."
                                    }
                                }
                            },
                            'sc_discount': {
                                validators: {
                                    notEmpty: {
                                        message: "Senior Citizen discount is required."
                                    },
                                    integer: {
                                        message: "Invalid number format.",
                                        decimalSeparator: "."
                                    }
                                }
                            },
                            'pwd_discount': {
                                validators: {
                                    notEmpty: {
                                        message: "PWD discount is required."
                                    },
                                    integer: {
                                        message: "Invalid number format.",
                                        decimalSeparator: "."
                                    }
                                }
                            },
                            'rate': {
                                validators: {
                                    notEmpty: {
                                        message: "TAX Rate is required."
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
                    }
                );

                // Handle Form submit
                submitButton.addEventListener('click', function (e) {
                    // Prevent button default action
                    e.preventDefault();

                    // Validate Form
                    validator.validate().then(function (status) {
                        // Check Unit Fields
                        var unit_ids = $("input[name='products[]']").map(function () {
                            if ($(this).val()) {
                                return true;
                            } else {
                                return false;
                            }
                        }).get();
                        // Qtys
                        var qty_fields = $("input[name='qty[]']").map(function () {
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
                        // Prices
                        var prices_fields = $("input[name='price[]']").map(function () {
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

                        if (status == 'Valid' && checker(unit_ids) && checker(qty_fields) && checker(prices_fields)) {
                            // Show loading indication
                            submitButton.setAttribute('data-kt-indicator', 'on');
                            // Disable button to avoid multiple click
                            submitButton.disabled = true;
                            form.submit();
                            // alert('Form is submitted');
                        }
                    });
                });
            }
        });
    </script>
@endsection
