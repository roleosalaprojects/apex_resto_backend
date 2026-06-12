<nav class="main-header navbar navbar-expand navbar-dark navbar-purple">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="{{ route('admin.home') }}" class="nav-link">Home</a>
      </li>
    </ul>


    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Messages Dropdown Menu -->
      <li class="nav-item">
        <li class="nav-item dropdown">
            <a id="dropdownSubMenu1" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-toggle">Options&nbsp</a>
            <ul aria-labelledby="dropdownSubMenu1" class="dropdown-menu border-0 shadow" style="left: 0px; right: inherit;">
              <li><a href="{{route('profile')}}" class="dropdown-item"><i class="fa fa-user-cog" aria-hidden="true"></i>&nbspProfile </a></li>
              <li class="dropdown-divider"></li>
              {{-- <li><a href="{{url('superadmin/logout')}}" class="dropdown-item"><i class="fa fa-sign-out-alt" aria-hidden="true"></i>&nbspLogout</a></li> --}}
              <li>
                <a class="dropdown-item" href="{{ route('logout') }}"
                    onclick="event.preventDefault();
                                  document.getElementById('logout-form').submit();">
                    {{ __('Logout') }}
                </a>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
              </li>
            </ul>
          {{-- </li><a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button"><i class="fa fa-sign-out-alt" aria-hidden="true"></i></a> --}}
      </li>
    </ul>
  </nav>
