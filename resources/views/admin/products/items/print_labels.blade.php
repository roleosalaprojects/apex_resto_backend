@extends('admin.layouts.master')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{route('items.index')}}">Items</a></li>
    <li class="breadcrumb-item active">Print Labels</li>
@endsection
@section('title')
    Print Labels
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6 col-lg-5 col-xl-4">
            {!! Form::open(['route'=>'ready-items']) !!}
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-6">
                            <h3>Select Items</h3>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success float-right" onclick="GetAllItems()">
                                All items
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <table class="table table-hover table-bordered">
                                <thead>
                                <th>Item name</th>
                                </thead>
                                <tbody id="tbody">
                                {{-- Items Here --}}
                                </tbody>
                                <tfoot>
                                <tr>
                                    <td>
                                        <div class="form-group">
                                            <select class="select-item select2-selection select2-selection--single form-control"
                                                    name="search" id="search">
                                                @foreach ($items as $item)
                                                    <option value="{{$item->id}}-{{$item->unit_id}}">{{$item->name}} -
                                                        @if ($item->unit == "")
                                                            PC
                                                        @else
                                                            {{$item->unit}}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    {!! Form::button('Ready', ['class'=>'btn btn-success btn-lg float-right', 'type'=>'submit']) !!}
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
    <div class="error">

    </div>
@endsection
@section('script')
    <script>
        $(document).ready(function () {
            $('.select-item').val(null).trigger('change');
            $('.select-item').select2({
                theme: 'bootstrap4'
            });
            $(".select-item").on('change', function () {
                var data = $(".select-item option:selected").val();
                $name = data;
                if ($name) {
                    console.log(data);
                    $.ajax({
                        type: 'get',
                        url: '{{route("get-item-for-label")}}',
                        data: {'name': $name},
                        success: function (data) {
                            $('#tbody').append(data);
                            console.log(data);
                            $('.select-item').val(null).trigger('change');
                        },
                        error: function (data) {
                            console.log(data);
                        }
                    });
                }
            });
        });

        function GetAllItems() {
            $.ajax({
                type: 'get',
                url: '{{route("get-all-items-for-label")}}',
                success: function (data) {
                    console.log(data);
                    $('#tbody').append(data);
                },
                error: function (data) {
                    $('.error').append(data);
                    console.log(data);
                }
            });
        }
    </script>
@endsection
