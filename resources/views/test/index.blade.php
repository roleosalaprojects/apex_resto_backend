@extends('layout.app')
@section('header')
    Test Features
@endsection
@section('title')
    Test Features
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-header">
                    <h3 class="card-title">Send Email</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('test.mail') }}" method="post">
                        @csrf
                        <!--begin::basic autosize textarea-->
                        <div class="rounded border d-flex flex-column p-10 mb-6">
                            <label for="" class="form-label">Basic autosize textarea</label>
                            <textarea class="form-control" data-kt-autosize="true" name="payload"></textarea>
                        </div>
                        <!--end::basic autosize textarea-->
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Send!</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection