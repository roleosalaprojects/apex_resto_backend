<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Apex SuperAdmin</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- DataTables (plain build — page has no Bootstrap CSS, and the
         custom overrides below target the plain `.dataTables_*` classes,
         not the Bootstrap `.page-link` / `.page-item` ones) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #64748b;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --dark: #1e293b;
            --darker: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 260px;
            --header-height: 64px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            color: #334155;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--darker) 0%, var(--dark) 100%);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            margin-right: 12px;
        }

        .sidebar-brand-text {
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .sidebar-brand-text span {
            color: var(--primary-light);
        }

        .sidebar-user {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .sidebar-user-info {
            flex: 1;
        }

        .sidebar-user-name {
            color: white;
            font-weight: 500;
            font-size: 14px;
        }

        .sidebar-user-role {
            color: #94a3b8;
            font-size: 12px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }

        .sidebar-nav-title {
            color: #64748b;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem 1.5rem;
            margin-top: 0.5rem;
        }

        .sidebar-nav-item {
            padding: 0 0.75rem;
            margin-bottom: 2px;
        }

        .sidebar-nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .sidebar-nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .sidebar-nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .sidebar-nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        .main-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .sidebar-toggle:hover {
            background: var(--light);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            color: var(--secondary);
            font-size: 14px;
        }

        .breadcrumb-item a {
            color: var(--secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb-item a:hover {
            color: var(--primary);
        }

        .breadcrumb-item.active {
            color: var(--dark);
            font-weight: 500;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: '/';
            margin-right: 0.5rem;
            color: #cbd5e1;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-dropdown {
            position: relative;
        }

        .header-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            color: var(--dark);
            transition: all 0.2s;
        }

        .header-dropdown-toggle:hover {
            border-color: var(--primary);
        }

        .header-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            z-index: 1000;
            overflow: hidden;
        }

        .header-dropdown-menu.show {
            display: block;
        }

        .header-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.2s;
        }

        .header-dropdown-item:hover {
            background: var(--light);
        }

        .header-dropdown-item.danger {
            color: var(--danger);
        }

        .header-dropdown-divider {
            height: 1px;
            background: var(--border);
            margin: 0.25rem 0;
        }

        /* Page Content */
        .page-content {
            flex: 1;
            padding: 1.5rem;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: var(--secondary);
            font-size: 14px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--dark);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 13px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 8px;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 1rem;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.2s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--danger);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.25rem;
            padding-right: 2.5rem;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.875rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--secondary);
            background: var(--light);
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            font-size: 12px;
            font-weight: 500;
            border-radius: 6px;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #e0f2fe;
            color: #075985;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert i {
            font-size: 1.125rem;
            margin-top: 0.125rem;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, var(--success) 0%, #16a34a 100%);
            color: white;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
            color: white;
        }

        .stat-icon.info {
            background: linear-gradient(135deg, var(--info) 0%, #0284c7 100%);
            color: white;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 13px;
            color: var(--secondary);
            margin-top: 0.25rem;
        }

        /* Footer */
        .main-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: var(--secondary);
        }

        .main-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        /* Grid */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.75rem;
        }

        .col, [class*="col-"] {
            padding: 0.75rem;
        }

        .col { flex: 1; }
        .col-12 { width: 100%; }
        .col-6 { width: 50%; }
        .col-4 { width: 33.333%; }
        .col-3 { width: 25%; }
        .col-8 { width: 66.666%; }

        /* DataTables Override */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin-left: 0.5rem;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.375rem 2rem 0.375rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px !important;
            margin: 0 2px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            color: white !important;
        }

        /* Utilities */
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .text-muted { color: var(--secondary); }
        .float-right { float: right; }
        .mb-0 { margin-bottom: 0; }
        .mb-3 { margin-bottom: 1rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-3 { margin-top: 1rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .col-6, .col-4, .col-3 {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .breadcrumb {
                display: none;
            }

            .page-title {
                font-size: 1.25rem;
            }
        }
    </style>
    @yield('style')
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="app-wrapper">
        @include('superadmin.layouts.main-sidebar')

        <div class="main-content">
            @include('superadmin.layouts.navbar')

            <div class="page-content">
                @include('superadmin.layouts.message')
                @yield('content')
            </div>

            <footer class="main-footer">
                <div>
                    <strong>&copy; {{ date('Y') }} <a href="/superadmin">Apex POS</a></strong> - All rights reserved.
                </div>
                <div>
                    Version 2.0.0
                </div>
            </footer>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables (plain build) -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Sidebar toggle
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarToggle = document.getElementById('sidebarToggle');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                sidebarOverlay.classList.toggle('show');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            });
        }

        // Dropdown toggle
        document.querySelectorAll('.header-dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const menu = toggle.nextElementSibling;
                document.querySelectorAll('.header-dropdown-menu').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                menu.classList.toggle('show');
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.header-dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        });

        // Prevent form submit on enter
        $(document).ready(function () {
            $(window).keydown(function (event) {
                if (event.keyCode == 13 && event.target.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    return false;
                }
            });
        });
    </script>
    @yield('script')
</body>
</html>
