<!--begin::User account menu-->
<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
    <!--begin::Menu item-->
    <div class="menu-item px-3">
        <div class="menu-content d-flex align-items-center px-3">
            <!--begin::Avatar-->
            <div class="symbol symbol-50px me-5">
                <img alt="Logo" src="{{ (auth()->user()->details?->image) ? asset(auth()->user()->details->image) : asset("assets/media/avatars/blank.png") }}"/>
            </div>
            <!--end::Avatar-->
            <!--begin::Username-->
            <div class="d-flex flex-column">
                <div class="fw-bold d-flex align-items-center fs-5">
                    {{ auth()->user()->name }} <span
                            class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">{{ auth()->user()->role->name }}</span>
                </div>
                <a href="#" class="fw-semibold text-muted text-hover-primary fs-7">
                    {{ auth()->user()->email }}                </a>
            </div>
            <!--end::Username-->
        </div>
    </div>
    <!--end::Menu item-->
    <!--begin::Menu separator-->
    <div class="separator my-2"></div>
    <!--end::Menu separator-->
    <!--begin::Menu item-->
    <div class="menu-item px-5 my-1">
        <a href="{{ route('profile') }}" class="menu-link px-5">
            Account Settings
        </a>
    </div>
    <!--end::Menu item-->
    <!--begin::Menu item-->
    <div class="menu-item px-5">
        <a class="menu-link px-5" href="{{ route('logout') }}"
           onclick="event.preventDefault();
			document.getElementById('logout-form').submit();">
            Sign Out
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>
    <!--end::Menu item-->
</div>
<!--end::User account menu-->