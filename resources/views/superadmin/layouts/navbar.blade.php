<header class="main-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <ol class="breadcrumb">
            @yield('breadcrumb')
        </ol>
    </div>

    <div class="header-right">
        <div class="header-dropdown">
            <button class="header-dropdown-toggle">
                <i class="fas fa-user-circle"></i>
                <span>{{ auth()->guard('superadmin')->check() ? auth()->guard()->user()->name : 'Account' }}</span>
                <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
            </button>
            <div class="header-dropdown-menu">
                <a href="#" class="header-dropdown-item">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </a>
                <div class="header-dropdown-divider"></div>
                <a href="{{ url('superadmin/logout') }}" class="header-dropdown-item danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>
