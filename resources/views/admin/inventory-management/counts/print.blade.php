@extends('admin.printer.default')
@section('title')
    Print IC #{{$count->ic}}
@endsection
@section('content')
    <div class="row invoice-info">
        <div class="col-sm-4 invoice-col">
            <h4>Location:</h4>
            <address>
                <h4>
                    <strong>
                        {{$count->store->name}}
                    </strong>
                </h4>
            </address>

        </div>
        <div class="col-sm-4 invoice-col">
            Created By
            <address>
                <strong>{{auth()->user()->name}}</strong>
            </address>
        </div>
        <!-- /.col -->
        <div class="col-sm-4 invoice-col">
            <b>IC #:</b> {{$count->ic}} <br>
            <b>Created at:</b> {{Carbon\Carbon::parse($count->created_at)->format("M/d/Y h:i:s A")}}
        </div>
        <!-- /.col -->
    </div>
    <!-- /.row -->

    <!-- Table row -->
    <div class="row">
        <div class="col-12 table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Unit</th>
                    <th>Current Stock (per Piece)</th>
                    <th>Remaining</th>
                    <th>Unit</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($count->lines as $line)
                    <tr>
                        <td>{{$line->item->name}}</td>
                        <td>
                            {{($line->unit_id > 0) ? $line->unit->name : "PC"}}
                            ({{($line->item_unit->where('unit_id', $line->unit_id)->pluck('qty')->first() > 0) ? $line->item_unit->where('unit_id', $line->unit_id)->pluck('qty')->first() : "1"}}
                            )
                        </td>
                        <td>{{$line->item_stock()->where('store_id', $count->store_id)->pluck('stock')->first()}}</td>
                        <td>__________________</td>
                        <td>__________________</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <!-- /.col -->
    </div>
@endsection
