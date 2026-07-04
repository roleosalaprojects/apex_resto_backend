  <aside class="main-sidebar sidebar-dark-teal elevation-4">
    <!-- Brand Logo -->
    <a href="{{url('/home')}}" class="brand-link">
      <img src="{{ url('dist/img/rol.png') }}" alt="Rolworks Logo" class="brand-image img-circle elevation-3"
           style="opacity: .8">
      <span class="brand-text font-weight-light">Rolworks</span>
    </a>
    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="{{(auth()->user()->details->image) ? asset(auth()->user()->details->image) : asset("dist/img/user2.png") }}" class="img-circle elevation-2" alt="User Image">
          {{-- <img src="" class="img-circle elevation-2" alt="User Image"> --}}
        </div>
        <div class="info">
          <a href="{{route('profile')}}" class="d-block">{{ auth()->check() ? auth()->guard()->user()->name : 'Account'}}</a>
        </div>
      </div>
{{-- Note that need class active for every page --}}
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          {{-- Dashboard --}}
          @if ($access->sls)
            <li class="nav-item">
              <a href="{{ route('admin.home') }}" class="nav-link {{ (request()->is('home*')) ? 'active' : '' }}">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>
                  Dashboard
                </p>
              </a>
            </li>
          @endif
          {{-- Reports --}}
          @if ($access->sls)
          <li class="nav-item has-treeview {{ (request()->is('admin/reports*')) ? 'menu-open' : '' }}">
            <a href="" class="nav-link {{ (request()->is('admin/reports*')) ? 'active' : '' }}">
              <i class="nav-icon fas fa-chart-bar"></i>
              <p>
                Reports
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('view.receipts')}}" class="nav-link {{ (request()->is('admin/reports/receipt*')) ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>
                    Sale Summary
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('sales.by.items')}}" class="nav-link {{ (request()->is('admin/reports/sales-by-item*')) ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>
                    Sales By Item
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('categories.summary')}}" class="nav-link {{ (request()->is('admin/reports/categories*')) ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>
                    Category Summary
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('suppliers.summary') }}" class="nav-link {{ (request()->is('admin/reports/suppliers*')) ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>
                    Suppliers Summary
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('pos.readings')}}" class="nav-link {{ (request()->is('admin/reports/readings*')) ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>
                    Readings
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('reports.peak_hours') }}" class="nav-link {{ request()->routeIs('reports.peak_hours') ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>Peak Hours</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('reports.profit_margins') }}" class="nav-link {{ request()->routeIs('reports.profit_margins') ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>Profit Margins</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('reports.scheduled.index') }}" class="nav-link {{ request()->routeIs('reports.scheduled.*') ? 'active' : '' }}">
                  <i class="nav-icon far fa-circle"></i>
                  <p>Scheduled Reports</p>
                </a>
              </li>
            </ul>
          </li>
          @endif
          {{-- BIR Reports --}}
          @if ($access->sls)
            <li class="nav-item has-treeview {{ (request()->is('admin/bir*')) ? 'menu-open' : '' }}">
              <a href="#" class="nav-link {{ (request()->is('admin/bir*')) ? 'active' : '' }}">
                <i class="nav-icon fas fa-list"></i>
                <p>
                  BIR
                  <i class="fas fa-angle-left right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="{{route('bir.vat')}}" class="nav-link {{ (request()->is('admin/bir/vat*')) ? 'active' : '' }}">
                    <i class="nav-icon far fa-circle"></i>
                    <p>
                      VAT Report
                    </p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{route('bir.non_vat')}}" class="nav-link {{ (request()->is('admin/bir/non-vat*')) ? 'active' : '' }}">
                    <i class="nav-icon far fa-circle"></i>
                    <p>
                      Non-VAT Report
                    </p>
                  </a>
                </li>
              </ul>
            </li>
          @endif
          {{-- Products --}}
          @if ($access->itms)
            <li class="nav-item has-treeview {{ (request()->is('admin/products*')) ? 'menu-open' : '' }}">
              <a href="#" class="nav-link {{ (request()->is('admin/products*')) ? 'active' : '' }}">
                <i class="nav-icon fas fa-shopping-bag"></i>
                <p>
                  Products
                  <i class="fas fa-angle-left right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="{{route('items.index')}}" class="nav-link {{ (request()->is('admin/products/items*')) ? 'active' : '' }}">
                    <i class="fas fa-list-alt nav-icon"></i>
                    <p>Items</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{route('categories.index')}}" class="nav-link {{ (request()->is('admin/products/categories*')) ? 'active' : '' }}">
                    <i class="fas fa-sitemap nav-icon"></i>
                    <p>Categories</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{route('uom.index')}}" class="nav-link {{ (request()->is('admin/products/uom*')) ? 'active' : '' }}">
                    <i class="fas fa-weight nav-icon"></i>
                    <p>Unit of Measure</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{route('discounts.index')}}" class="nav-link {{ (request()->is('admin/products/discount*')) ? 'active' : '' }}">
                    <i class="fas fa-percentage nav-icon"></i>
                    <p>Discounts</p>
                  </a>
                </li>
              </ul>
            </li>
          @endif

          @if ($access->trnsfrs || $access->adjstmnts || $access->spplrs || $access->prchs || $access->invntry)
            <li class="nav-item has-treeview {{ (request()->is('admin/inventory*')) ? 'menu-open' : '' }}">
              <a href="#" class="nav-link {{ (request()->is('admin/inventory*')) ? 'active' : '' }}">
                <i class="nav-icon fas fa-box"></i>
                <p>
                  Inventory Management
                  <i class="fas fa-angle-left right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                {{-- Price Changes --}}
                {{-- Need access --}}
                {{-- <li class="nav-item">
                  <a href="{{route('prices.index')}}" class="nav-link {{ (request()->is('admin/inventory/prices*')) ? 'active' : '' }}">
                    <i class="fas fa-money-check-alt nav-icon"></i>
                    <p>Price Changes</p>
                  </a>
                </li> --}}
                {{-- Purchase Orders --}}
                @if ($access->prchs)
                  <li class="nav-item">
                    <a href="{{route('purchases.index')}}" class="nav-link {{ (request()->is('admin/inventory/purchases*')) ? 'active' : '' }}">
                      <i class="fas fa-clipboard-list nav-icon"></i>
                      <p>Purchase Orders</p>
                    </a>
                  </li>
                @endif

                {{-- Inventory Counts --}}
                @if ($access->invntry)
                  <li class="nav-item">
                    <a href="{{route('counts.index')}}" class="nav-link {{ (request()->is('admin/counts*')) ? 'active' : '' }}">
                      <i class="fas fa-list-ol nav-icon"></i>
                      <p>Inventory Counts</p>
                    </a>
                  </li>
                @endif

                {{-- Stock Adjustments --}}
                @if ($access->adjstmnts)
                  <li class="nav-item">
                    <a href="{{route('adjustments.index')}}" class="nav-link {{ (request()->is('admin/inventory/adjustments*')) ? 'active' : '' }}">
                      <i class="fas fa-sliders-h nav-icon"></i>
                      <p>Stock Adjustments</p>
                    </a>
                  </li>
                @endif
                {{-- Transfer Orders --}}
                @if ($access->trnsfrs)
                  <li class="nav-item">
                    <a href="{{route('transfers.index')}}" class="nav-link {{ (request()->is('admin/inventory/transfers*')) ? 'active' : '' }}">
                      <i class="fas fa-exchange-alt nav-icon"></i>
                      <p>Transfer Orders</p>
                    </a>
                  </li>
                @endif
                {{-- Suppliers --}}
                @if ($access->spplrs)
                  <li class="nav-item">
                    <a href="{{route('suppliers.index')}}" class="nav-link {{ (request()->is('admin/inventory/suppliers*')) ? 'active' : '' }}">
                      <i class="fas fa-address-book nav-icon"></i>
                      <p>Suppliers</p>
                    </a>
                  </li>
                @endif
              </ul>
            </li>
          @endif
          {{-- Employees --}}
          @if ($access->emplys || $access->rl)
            <li class="nav-item has-treeview {{ (request()->is('admin/employees*')) ? 'menu-open' : '' }}">
              <a href="" class="nav-link {{ (request()->is('admin/employees*')) ? 'active' : '' }}">
                <i class="nav-icon fas fa-id-card"></i>
                <p>
                  Employee Management
                  <i class="fas fa-angle-left right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                {{-- @if ($access->emplys)
                  <li class="nav-item has-treeview {{ (request()->is('admin/employees*')) ? 'menu-open' : '' }}">
                    <a href="" class="nav-link {{ (request()->is('admin/employees*')) ? 'active' : '' }}">
                      <i class="fas fa-admin nav-icon"></i>
                      <p>
                        Employees
                        <i class="right fas fa-angle-left"></i>
                      </p>
                    </a>
                    <ul class="nav nav-treeview">
                      <li class="nav-item">
                        <a href="{{route('employees.index')}}" class="nav-link {{ (request()->is('admin/employees/employees*')) ? 'active' : '' }}">
                          <i class="far fa-dot-circle nav-icon"></i>
                          <p>Employee List</p>
                        </a>
                      </li>
                      <li class="nav-item">
                        <a href="{{route('schedules.index')}}" class="nav-link {{ (request()->is('admin/employees/schedules*')) ? 'active' : '' }}">
                          <i class="far fa-dot-circle nav-icon"></i>
                          <p>Schedules</p>
                        </a>
                      </li>
                      <li class="nav-item">
                        <a href="{{route('deductions.index')}}" class="nav-link {{ (request()->is('admin/employees/deductions*')) ? 'active' : '' }}">
                          <i class="far fa-dot-circle nav-icon"></i>
                          <p>Deductions</p>
                        </a>
                      </li>
                    </ul>
                  </li>
                @endif --}}
                @if ($access->emplys)
                  <li class="nav-item">
                    <a href="{{route('employees.index')}}" class="nav-link {{ (request()->is('admin/employees/employees*')) ? 'active' : '' }}">
                      <i class="fas fa-users nav-icon"></i>
                      <p>Employee List</p>
                    </a>
                  </li>
                @endif
                @if($access->rl)
                  <li class="nav-item">
                    <a href="{{route('roles.index')}}" class="nav-link {{ (request()->is('admin/employees/roles*')) ? 'active' : '' }}">
                      <i class="fas fa-user-tag nav-icon"></i>
                      <p>Access Rights</p>
                    </a>
                  </li>
                @endif
              </ul>
            </li>
          @endif
          @if ($access->cstmr)
            {{-- Customers --}}
            <li class="nav-item">
              <a href="{{route('customers.index')}}" class="nav-link {{ (request()->is('admin/customers*')) ? 'active' : ''}}">
                <i class="nav-icon fas fa-user-friends"></i>
                <p>
                  Customers
                </p>
              </a>
            </li>
          @endif
          @if ($access->str)
            {{-- Stores --}}
            <li class="nav-item">
              <a href="{{route('stores.index')}}" class="nav-link {{ (request()->is('admin/stores*')) ? 'active' : ''}}">
                <i class="nav-icon fas fa-store-alt"></i>
                <p>
                  Stores
                </p>
              </a>
            </li>
          @endif
          @if ($access->tax)
            {{-- Taxes --}}
            <li class="nav-item">
              <a href="{{route('taxes.index')}}" class="nav-link {{ (request()->is('admin/taxes*')) ? 'active' : ''}}">
                <i class="nav-icon fas fa-file-invoice"></i>
                <p>
                  Taxes
                </p>
              </a>
            </li>
          @endif
          @if ($access->sttngs)
              <li class="nav-item has-treeview {{ (request()->is('admin/settings*')) ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ (request()->is('admin/settings*')) ? 'active' : '' }}">
                  <i class="nav-icon fas fa-cog"></i>
                  <p>
                    Settings
                    <i class="fas fa-angle-left right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  {{-- <li class="nav-item">
                    <a href="{{route('receipt')}}" class="nav-link {{ (request()->is('admin/settings/receipt*')) ? 'active' : '' }}">
                      <i class="fas fa-receipt nav-icon"></i>
                      <p>Receipt</p>
                    </a>
                  </li> --}}
                  <li class="nav-item">
                    <a href="{{route('pos.index')}}" class="nav-link {{ (request()->is('admin/settings/pos*')) ? 'active' : '' }}">
                      <i class="fas fa-desktop nav-icon"></i>
                      <p>POS Devices</p>
                    </a>
                  </li>
                  {{-- <li class="nav-item">
                    <a href="{{route("settings.index")}}" class="nav-link {{ (request()->is('admin/settings/p-settings*')) ? 'active' : '' }}">
                      <i class="fas fa-receipt nav-icon"></i>
                      <p>POS Settings</p>
                    </a>
                  </li> --}}
                  <li class="nav-item">
                    <a href="{{route('backup.db')}}" class="nav-link">
                      <i class="fas fa-file-export nav-icon"></i>
                      <p>Backup Database</p>
                    </a>
                  </li>
                </ul>
              </li>
          @endif
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>
