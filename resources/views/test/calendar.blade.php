
@extends('layout.app')
@section('title')
    Fullcalendar
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
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function(){
            var todayDate = moment().startOf("day");
            var YM = todayDate.format("YYYY-MM");
            var YESTERDAY = todayDate.clone().subtract(1, "day").format("YYYY-MM-DD");
            var TODAY = todayDate.format("YYYY-MM-DD");
            var TOMORROW = todayDate.clone().add(1, "day").format("YYYY-MM-DD");
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
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

                select: (arg) => {
                    Swal.fire({
                        html: `
                          <div class="mb-7">
                            Create new event?
                          </div>
                          <div class="fw-bold mb-5">
                            Event Name:
                          </div>
                          <input type="text" class="form-control" name="event_name" />
                        `,
                        icon: "info",
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: "Yes, create it!",
                        cancelButtonText: "No, return",
                        customClass: {
                            confirmButton: "btn btn-primary",
                            cancelButton: "btn btn-active-light"
                        }
                    }).then(function (result) {
                        console.log(arg)
                        if (result.value) {
                            var title = document.querySelector('input[name="event_name"]').value;
                            if (title) {
                                calendar.addEvent({
                                    title: title,
                                    start: arg.start,
                                    end: arg.end,
                                    allDay: arg.allDay,
                                    color: 'purple',
                                })
                            }
                            calendar.unselect()
                        } else if (result.dismiss === "cancel") {
                            Swal.fire({
                                text: "Event creation was declined!.",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn btn-primary",
                                }
                            });
                        }
                    });
                },
                // Delete event
                eventClick: function (arg) {
                    Swal.fire({
                        text: "Are you sure you want to delete this event?",
                        icon: "warning",
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
                        } else if (result.dismiss === "cancel") {
                            Swal.fire({
                                text: "Event was not deleted!.",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn btn-primary",
                                }
                            });
                        }
                    });
                },

                initialView: "dayGridMonth",
                initialDate: TODAY,

                editable: true,
                selectable: true,
                dayMaxEvents: true, // allow "more" link when too many events
                navLinks: true,
                events: '{{ route("tests.calendar.data") }}',
                timeZone: 'Asia/Manila'
            });
            calendar.render();
        })
    </script>
@endsection