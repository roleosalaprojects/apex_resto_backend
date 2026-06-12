<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">A</div>
        <div class="sidebar-brand-text">Apex<span>Admin</span></div>
    </div>

    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            {{ auth()->guard('superadmin')->check() ? strtoupper(substr(auth()->guard()->user()->name, 0, 1)) : 'A' }}
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name">{{ auth()->guard('superadmin')->check() ? auth()->guard()->user()->name : 'Account' }}</div>
            <div class="sidebar-user-role">Super Administrator</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-nav-title">Main Menu</div>

        <div class="sidebar-nav-item">
            <a href="{{ route('dashboard') }}" class="sidebar-nav-link {{ request()->is('superadmin') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </div>

        <div class="sidebar-nav-title">Management</div>

        <div class="sidebar-nav-item">
            <a href="{{ route('admin.index') }}" class="sidebar-nav-link {{ request()->is('superadmin/admin*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                Users
            </a>
        </div>

        <div class="sidebar-nav-item">
            <a href="{{ route('receipt.index') }}" class="sidebar-nav-link {{ request()->is('superadmin/receipt*') ? 'active' : '' }}">
                <i class="fas fa-receipt"></i>
                Receipt Settings
            </a>
        </div>

        <div class="sidebar-nav-item">
            <a href="{{ route('superadmin.priority-items.index') }}" class="sidebar-nav-link {{ request()->is('superadmin/priority-items*') ? 'active' : '' }}">
                <i class="fas fa-star"></i>
                Priority Items
            </a>
        </div>

        <div class="sidebar-nav-title">Appearance</div>

        <div class="sidebar-nav-item">
            <a href="{{ route('superadmin.color-palettes.index') }}" class="sidebar-nav-link {{ request()->is('superadmin/color-palettes*') ? 'active' : '' }}">
                <i class="fas fa-palette"></i>
                Color Palettes
            </a>
        </div>

        <div class="sidebar-nav-title">System</div>

        <div class="sidebar-nav-item">
            <a href="{{ url('superadmin/logout') }}" class="sidebar-nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>
</aside>
