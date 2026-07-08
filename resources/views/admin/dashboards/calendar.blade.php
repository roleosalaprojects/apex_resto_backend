@extends('layout.app')
@section('header')
    - Calendar
@endsection
@section('title')
    Calendar
@endsection
@section('breadcrumb')
    
@endsection
@section('actions')
    
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}">
    <style>
        /* Dragged/picked dates must be unmistakable while the dialog is open. */
        .fc .fc-highlight {
            background: rgba(20, 115, 90, 0.16) !important;
            border: 1px dashed rgba(20, 115, 90, 0.65);
        }
        .fc .fc-day.fc-day-today {
            background: rgba(246, 192, 0, 0.06) !important;
        }
    </style>
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
@endsection
@section('scripts')
    
    <script>
        $(document).ready(function(){
            let todayDate = moment().startOf("day");
            let YM = todayDate.format("YYYY-MM");
            let YESTERDAY = todayDate.clone().subtract(1, "day").format("YYYY-MM-DD");
            let TODAY = todayDate.format("YYYY-MM-DD");
            let TOMORROW = todayDate.clone().add(1, "day").format("YYYY-MM-DD");
            let calendarEl = document.getElementById('calendar');
            // Initialize toastr for notifications
            toastrOptions();
            let calendar = new FullCalendar.Calendar(calendarEl, {
                headerToolbar: {
                    left: "prev,next today",
                    center: "title",
                    right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth"
                },

                height: 750,
                contentHeight: 780,
                aspectRatio: 1.38,  // see: https://fullcalendar.io/docs/aspectRatio

                nowIndicator: true,
                now: TODAY + "T09:25:00", // just for demo

                views: {
                    dayGridMonth: { buttonText: "month" },
                    timeGridWeek: { buttonText: "week" },
                    timeGridDay: { buttonText: "day" }
                },
                // Drag (or tap) dates to book: the range stays highlighted
                // while the dialog is open, and the dialog names the dates
                // so there is no guessing what was picked.
                select: (arg) => {
                    openReservationDialog(arg);
                },
                unselectAuto: false,
                eventDrop: function (arg) {
                    updateCalendar(arg)
                },
                eventResize: function(arg){
                    updateCalendar(arg)
                },
                // Delete event
                eventClick: function (arg) {
                    calendarClickEvents(arg);

                },

                initialView: "dayGridMonth",
                initialDate: TODAY,

                editable: true,
                selectable: true,
                dayMaxEvents: true, // allow "more" link when too many events
                navLinks: true,
                eventSources: [
                    {
                        url: '{{ route('calendars.events') }}'
                    },
                    {
                        url: '{{ route('calendars.purchases') }}',
                        editable: false,
                    },
                    @if(auth()->user()->role->rstrnt)
                    {
                        url: '{{ route('calendars.reservations') }}',
                        editable: false,
                    },
                    @endif
                    @if(auth()->user()->role->sls)
                    {
                        url: '{{ route("calendar.salesData") }}',
                        editable: false,
                    },
                    @endif
                ],
                timeZone: 'Asia/Manila'
            });
            calendar.render();

            function selectionLabel(arg) {
                const start = moment(arg.start);
                const endInclusive = moment(arg.end).subtract(1, 'day');
                if (endInclusive.isSame(start, 'day')) {
                    return start.format('ddd · MMM D, YYYY');
                }
                return start.format('MMM D') + ' → ' + endInclusive.format('MMM D, YYYY');
            }

            function openReservationDialog(arg) {
                const start = moment(arg.start);
                const multiDay = !moment(arg.end).subtract(1, 'day').isSame(start, 'day');
                Swal.fire({
                    ...createSwal(`
                        <div class="mb-2 fs-6 text-muted">Booking for</div>
                        <div class="mb-6 fs-2 fw-bolder">${selectionLabel(arg)}</div>
                        ${multiDay ? '<div class="mb-5 badge badge-light-warning">Reservation books the first day</div>' : ''}
                        <div class="form-group mb-3 text-start">
                            <label class="form-label required">Guest name</label>
                            <input type="text" class="form-control" id="rsv_name" />
                        </div>
                        <div class="row">
                            <div class="col-6 form-group mb-3 text-start">
                                <label class="form-label">Time</label>
                                <input type="time" class="form-control" id="rsv_time" value="19:00" />
                            </div>
                            <div class="col-6 form-group mb-3 text-start">
                                <label class="form-label">Party size</label>
                                <input type="number" min="1" class="form-control" id="rsv_party" value="2" />
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 form-group mb-3 text-start">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" id="rsv_phone" />
                            </div>
                            <div class="col-6 form-group mb-3 text-start">
                                <label class="form-label">Table</label>
                                <select class="form-select" id="rsv_table">
                                    <option value="">— Any table —</option>
                                    @foreach ($tables as $table)
                                        <option value="{{ $table->id }}">{{ $table->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-2 text-start">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" id="rsv_notes" />
                        </div>
                    `),
                    confirmButtonText: 'Book reservation',
                    showDenyButton: true,
                    denyButtonText: 'Plain event',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        denyButton: 'btn btn-light',
                        cancelButton: 'btn btn-active-light',
                    },
                }).then(function (result) {
                    if (result.isDenied) {
                        openEventDialog(arg);
                        return;
                    }
                    if (result.value) {
                        const name = document.getElementById('rsv_name').value.trim();
                        if (!name) {
                            toastr.error('Guest name is required');
                            calendar.unselect();
                            return;
                        }
                        $.ajax({
                            url: '{{ route('reservations.store') }}',
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            data: {
                                name: name,
                                phone: document.getElementById('rsv_phone').value,
                                party_size: document.getElementById('rsv_party').value || 1,
                                reserved_at: start.format('YYYY-MM-DD') + ' ' +
                                    (document.getElementById('rsv_time').value || '19:00'),
                                table_id: document.getElementById('rsv_table').value || null,
                                notes: document.getElementById('rsv_notes').value,
                            },
                            success: function (response) {
                                toastr.success(response.message);
                                calendar.refetchEvents();
                            },
                            error: function (xhr) {
                                toastr.error(xhr.responseJSON?.message || 'Could not book the reservation');
                            },
                        });
                    }
                    calendar.unselect();
                });
            }

            function openEventDialog(arg) {
                Swal.fire(
                    createSwal(
                        `
                        <div class="mb-2 fs-6 text-muted">New event on</div>
                        <div class="mb-6 fs-2 fw-bolder">${selectionLabel(arg)}</div>
                        <div class="form-group mb-3 text-start">
                            <label class="form-label">Event Name</label>
                            <input type="text" class="form-control" name="event_name" />
                        </div>
                        <div class="form-group mb-3 text-start">
                            <label for="" class="form-label">Color</label>
                            <select class="form-select" id="event_color" placeholder="Select Color">
                                <option value="{{Config::get('colors.primary')}}">Blue</option>
                                <option value="{{Config::get('colors.teal')}}">Teal</option>
                                <option value="{{Config::get('colors.danger')}}">Red</option>
                                <option value="{{Config::get('colors.success')}}">Green</option>
                                <option value="{{Config::get('colors.info')}}">Purple</option>
                                <option value="{{Config::get('colors.warning')}}">Yellow</option>
                            </select>
                        </div>
                        `
                    )
                ).then(function (result) {
                    if (result.value) {
                        var title = document.querySelector('input[name="event_name"]').value;
                        var color = document.querySelector('#event_color').value;
                        if (title) {
                            $.ajax({
                                url: '{{ route('calendars.store') }}',
                                method: 'POST',
                                data: {
                                    title: title,
                                    start: moment(arg.start).format('YYYY-MM-DD HH:ss'),
                                    end: moment(arg.end).format('YYYY-MM-DD HH:ss'),
                                    allDay: true,
                                    color: color,
                                },
                                success: function (response){
                                    toastr.success(response.message);
                                    calendar.addEvent({
                                        id: response.id,
                                        title: title,
                                        start: arg.start,
                                        end: arg.end,
                                        allDay: arg.allDay,
                                        color: color,
                                    })
                                }
                            })
                        }
                    }
                    calendar.unselect();
                });
            }

            function calendarClickEvents(arg){
                let eventExt = arg.event.extendedProps;
                let eventType = arg.event.extendedProps.type;
                if(eventType === 'reservation'){
                    Swal.fire(
                        infoSwalCustom(`
                            <div class="mb-6"><span class="fs-3">${eventExt.guest} · ${eventExt.party_size} pax</span></div>
                            <div class="mb-3"><span class="fs-5">${moment(arg.event.start).format('ddd · MMM D, YYYY · h:mm A')}</span></div>
                            <div class="mb-3"><span class="fs-5">Table: ${eventExt.table || 'Any'}</span></div>
                            ${eventExt.phone ? `<div class="mb-3"><span class="fs-5">Phone: ${eventExt.phone}</span></div>` : ''}
                            <div class="mb-3"><span class="badge badge-light-primary text-uppercase">${eventExt.status}</span></div>
                            ${eventExt.notes ? `<div class="mb-3 text-muted">${eventExt.notes}</div>` : ''}
                        `)
                    ).then(function(result){
                        if(result.value){
                            window.location.href = '/admin/reservations/' + eventExt.reservation_id + '/edit';
                        }
                    });
                    return;
                }
                if(eventType === 'purchase'){
                    var progress = "";
                    var style = "";
                    var width = 0;
                    if(eventExt.items - eventExt.received > 0){
                        style = "bg-info";
                    }else if(eventExt.items - eventExt.received == 0){
                        style = "bg-success";
                    }else{
                        style = "bg-primary";
                    }
                    if(eventExt.received >= 0){
                        width = (eventExt.received / eventExt.items) * 100;

                    }

                    Swal.fire(
                        infoSwalCustom(`
                            <div class="mb-6">
                             <span class="fs-3">PO# ${arg.event.extendedProps.po}</span>
                            </div>
                            <div class="mb-3">
                             <span class="fs-5">Supplier: ${arg.event.extendedProps.supplier}</span>
                            </div>
                            <div class="mb-3">
                             <span class="fs-5">Purchase Date: ${moment(arg.event.extendedProps.purchase_date).format('MMM D, YYYY')}</span>
                            </div>
                            <div class="mb-3">
                             <span class="fs-5">Due Date: ${moment(arg.event.start).format('MMM D, YYYY')}</span>
                            </div>
                            <div class="mb-3">
                             <span class="fs-5">Terms: ${arg.event.extendedProps.terms} days</span>
                            </div>
                            <div class="mb-3">
                                <small>Items: ${eventExt.received} of ${eventExt.items} received</small>
                            </div>
                            <div class="mb-3">
                             <span class="fs-5">Amount: ${accountingFormat(arg.event.extendedProps.total)}</span>
                            </div>
                            <div>

                            </div>
                        `)
                    ).then(function(result){
                        if(result.value){
                            // open a new linked to this purchase
                            let url = '/admin/purchases/' + arg.event.id
                            let newWindow = window.open(url, '_blank');
                          if (newWindow) {
                            newWindow.focus();
                          } else {
                            // Handle the case where the window could not be opened (e.g., pop-up blocked)
                            infoSwal('Pop-up Blocker',"Could not open new window. Pop-up blocker might be active.");
                            // Provide a fallback, e.g., redirecting the current window
                            // window.location.href = url;
                          }
                        }
                    });

                }else if(event == 'sale'){
                    // Do nothing...
                }else{
                    deleteCalendarEvent(arg);
                }
            }

            function updateCalendar(arg){
                $.ajax({
                    url: '/admin/calendars/' + arg.event.id,
                    method: 'PUT',
                    data: {
                        title: arg.event.title,
                        start: arg.event.start.toISOString(),
                        end: arg.event.end.toISOString(),
                        allDay: true,
                        color: arg.event.color,
                    },
                    success: function(response){
                        toastr.info(response.message);
                    }
                })
            }

            function deleteCalendarEvent(arg){
                if(arg.event.id){
                    Swal.fire({
                        text: "Are you sure you want to delete this event?",
                        icon: "info",
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: "Yes, delete it!",
                        cancelButtonText: "No, return",
                        customClass: {
                            confirmButton: "btn btn-primary",
                            cancelButton: "btn btn-active-light"
                        }
                    }).then(function (result) {
                        if (result.value) {
                            arg.event.remove()
                            $.ajax({
                                url: '/admin/calendars/' + arg.event.id,
                                method: 'DELETE',
                                success: function (response){
                                    toastr.warning(response.message);
                                }
                            })
                        }
                        // else if (result.dismiss === "cancel") {
                        //     Swal.fire({
                        //         text: "Event was not deleted!.",
                        //         icon: "error",
                        //         buttonsStyling: false,
                        //         confirmButtonText: "Ok, got it!",
                        //         customClass: {
                        //             confirmButton: "btn btn-primary",
                        //         }
                        //     });
                        // }
                    });
                }
            }
        })
    </script>
@endsection
