<div class="card">
    <div class="card-body">
        <div class="form-group mb-5">
            <label for="name" class="form-label required">Name</label>
            <input type="text" class="form-control" value="{{$pos->name}}" name="name"/>
            <span class="text-danger">{{$errors->has('name') ? "Name field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="serial" class="form-label">Serial</label>
            <input type="text" class="form-control" value="{{$pos->serial}}" name="serial"/>
        </div>
        <div class="form-group mb-5">
            <label for="min" class="form-label">MIN #</label>
            <input type="text" class="form-control" value="{{$pos->min}}" name="min"/>
        </div>
        <div class="form-group mb-5">
            <label for="ptu" class="form-label">PTU #</label>
            <input type="text" class="form-control" value="{{$pos->ptu}}" name="ptu"/>
        </div>
        <div class="form-group mb-5">
            <label for="issued" class="form-label">Issued On</label>
            <input type="text" class="form-control" value="{{$pos->issued}}" name="issued"/>
        </div>
        <div class="form-group mb-5">
            <label for="expiry" class="form-label">Expiry Date</label>
            <input type="text" class="form-control" value="{{$pos->expiry}}" name="expiry"/>
        </div>
        <div class="form-group mb-5">
            <label for="type" class="form-label required">POS Type</label>
            <select class="form-select" name="type">
                <option value="0">Cashier</option>
                <option value="1">Ordering Machine</option>
                <option value="3">Cashier</option>
            </select>
        </div>
        <div class="form-group mb-5">
            <label for="store" class="form-label required">Select store deployment</label>
            <select class="form-select" name="store">
                <option value="">-- Select Store --</option>
                @foreach($stores as $id => $name)
                    <option value="{{$id}}" {{$selected_store == $id ? 'selected' : ''}}>{{$name}}</option>
                @endforeach
            </select>
            <span class="text-danger">{{$errors->has('store') ? "Store field cannot be empty!" : ''}}</span>
        </div>
    </div>
</div>
