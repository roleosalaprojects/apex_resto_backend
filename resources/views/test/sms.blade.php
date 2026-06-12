@extends('layout.app')
@section('title')
    SMS Message - Test
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item active">TEST - SMS</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('sms.send') }}" method="post" id="sms_form">
                @csrf
                <div class="form-group mb-6">
                    <label for="recipient" class="form-label required">Recipient</label>
                    <input type="text" class="form-control" name="recipient">
                </div>
                <div class="form-group mb-6">
                    <label for="message" class="form-label required">Message</label>
                    <textarea class="form-control" name="message" id="message" cols="30" rows="10"></textarea>
                </div>
                <div class="d-flex  justify-content-end">
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>
@endsection