<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - SIM Kos & Guesthouse</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 64px;
            --color-primary-teal: #0f766e;
            --color-primary-dark: #0b1424;
            --color-sidebar-bg: #0f172a;
            --color-sidebar-hover: rgba(255, 255, 255, 0.07);
            --color-sidebar-active: #0f766e;
            --color-content-bg: #f8fafc;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--color-content-bg);
            color: #0f172a;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
        }

        /* App Wrapper */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Sidebar Styling */
        .app-sidebar {
            width: var(--sidebar-width);
            background-color: var(--color-sidebar-bg);
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #1e293b;
            z-index: 1040;
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Desktop Sticky Sidebar */
        @media (min-width: 992px) {
            .app-sidebar {
                position: sticky;
                top: 0;
                height: 100vh;
                flex-shrink: 0;
            }

            .sidebar-collapsed .app-sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
        }

        /* Mobile Offcanvas Sidebar */
        @media (max-width: 991.98px) {
            .app-sidebar.offcanvas-lg {
                width: 280px;
                max-width: 85vw;
            }
        }

        .sidebar-brand {
            height: var(--topbar-height);
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #1e293b;
            background-color: #0b1322;
        }

        .sidebar-brand-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #ffffff;
        }

        .sidebar-brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(15, 118, 110, 0.4);
        }

        .sidebar-brand-title {
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.3px;
        }

        .sidebar-brand-subtitle {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .sidebar-nav-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 0.75rem;
        }

        .sidebar-nav-container::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-nav-container::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }

        .sidebar-section-title {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            padding: 0.85rem 0.65rem 0.35rem;
            margin-top: 0.25rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            margin-bottom: 0.2rem;
        }

        .sidebar-link i.link-icon {
            font-size: 1.1rem;
            min-width: 1.25rem;
            text-align: center;
        }

        .sidebar-link:hover {
            color: #ffffff;
            background-color: var(--color-sidebar-hover);
        }

        .sidebar-link.active {
            color: #ffffff;
            background: linear-gradient(90deg, #0f766e 0%, #115e59 100%);
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.35);
        }

        .sidebar-link.active i.link-icon {
            color: #5eead4;
        }

        /* Collapsible Submenus */
        .sidebar-group-toggle {
            cursor: pointer;
            justify-content: space-between;
        }

        .sidebar-group-toggle .chevron {
            font-size: 0.75rem;
            transition: transform 0.2s ease;
        }

        .sidebar-group-toggle[aria-expanded="true"] .chevron {
            transform: rotate(90deg);
        }

        .sidebar-submenu {
            list-style: none;
            padding-left: 0;
            margin: 0.25rem 0 0.5rem 0.5rem;
            border-left: 2px solid #1e293b;
        }

        .sidebar-subitem {
            margin-bottom: 0.15rem;
        }

        .sidebar-sublink {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.75rem 0.45rem 1rem;
            border-radius: 0 6px 6px 0;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.825rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-sublink:hover {
            color: #ffffff;
            background-color: var(--color-sidebar-hover);
        }

        .sidebar-sublink.active {
            color: #2dd4bf;
            background-color: rgba(20, 184, 166, 0.12);
            font-weight: 600;
            border-left: 2px solid #2dd4bf;
            margin-left: -2px;
        }

        /* Sidebar Footer / User Profile */
        .sidebar-footer {
            padding: 0.85rem 1rem;
            border-top: 1px solid #1e293b;
            background-color: #0b1322;
        }

        .sidebar-user-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #1e293b;
            color: #2dd4bf;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid #334155;
            flex-shrink: 0;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.72rem;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Main Area (Topbar + Content) */
        .app-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background-color: var(--color-content-bg);
        }

        /* Topbar */
        .app-topbar {
            height: var(--topbar-height);
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .topbar-toggle-btn {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.4rem 0.65rem;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .topbar-toggle-btn:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .topbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        /* App Content */
        .app-content {
            flex: 1;
            padding: 1.5rem;
        }

        @media (max-width: 576px) {
            .app-content {
                padding: 1rem 0.75rem;
            }
        }

        /* Shared Component Utilities */
        .bg-primary-teal { background-color: #0f766e !important; }
        .text-primary-teal { color: #0f766e !important; }
        .btn-primary-teal {
            background-color: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        .btn-primary-teal:hover {
            background-color: #115e59;
            border-color: #115e59;
            color: #fff;
        }

        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        .kpi-card {
            transition: transform 0.15s ease-in-out;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
        }

        /* Status Badge Helpers */
        .badge-status-unpaid { background-color: #ef4444; color: #fff; }
        .badge-status-partially_paid { background-color: #f59e0b; color: #fff; }
        .badge-status-paid { background-color: #10b981; color: #fff; }
        .badge-status-pending { background-color: #f59e0b; color: #fff; }
        .badge-status-verified { background-color: #10b981; color: #fff; }
        .badge-status-rejected { background-color: #64748b; color: #fff; }

        /* Right-Side Drawer (Slide-in Panel) Standard */
        .offcanvas.offcanvas-end {
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
            border-left: 1px solid #e2e8f0;
            z-index: 1055;
        }

        .offcanvas-end.drawer-sm { width: 400px; }
        .offcanvas-end.drawer-md { width: 520px; }
        .offcanvas-end.drawer-lg { width: 640px; }
        .offcanvas-end.drawer-xl { width: 780px; }

        @media (max-width: 576px) {
            .offcanvas-end.drawer-sm,
            .offcanvas-end.drawer-md,
            .offcanvas-end.drawer-lg,
            .offcanvas-end.drawer-xl {
                width: 100% !important;
            }
        }

        .offcanvas .offcanvas-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
            background-color: #ffffff;
        }

        .offcanvas .offcanvas-body {
            padding: 1.25rem;
            background-color: #ffffff;
            overflow-y: auto;
        }

        .offcanvas .offcanvas-footer {
            border-top: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        /* Action Confirmation Modal Standard */
        .modal-confirm .modal-content {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .modal-confirm .modal-header {
            padding: 1.25rem 1.5rem 0.75rem;
            border-bottom: 0;
        }

        .modal-confirm .modal-body {
            padding: 0.5rem 1.5rem 1.25rem;
        }

        .modal-confirm .modal-footer {
            padding: 0.75rem 1.5rem 1.25rem;
            border-top: 0;
            background-color: #f8fafc;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="app-wrapper" id="appLayout">
        @auth
        <!-- APP SIDEBAR (Offcanvas for mobile/tablet, vertical sticky for desktop) -->
        <aside class="app-sidebar offcanvas-lg offcanvas-start" id="sidebarMenu" tabindex="-1" aria-labelledby="sidebarMenuLabel">
            <!-- Sidebar Header / Brand -->
            <div class="sidebar-brand">
                <a href="{{ route('dashboard.index') }}" class="sidebar-brand-link">
                    <div class="sidebar-brand-icon">
                        <i class="bi bi-buildings-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="sidebar-brand-title">SIM KOS</div>
                        <div class="sidebar-brand-subtitle">& Guesthouse</div>
                    </div>
                </a>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Tutup"></button>
            </div>

            <!-- Scrollable Nav -->
            <div class="sidebar-nav-container">
                <!-- 1. DASHBOARD & ANALITIK -->
                <div class="sidebar-section-title">Dashboard & Analitik</div>
                <nav class="nav flex-column">
                    <a class="sidebar-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">
                        <i class="bi bi-grid-fill link-icon"></i>
                        <span>Dashboard Utama</span>
                    </a>
                    @if(!auth()->user()->isTenant())
                    <a class="sidebar-link {{ request()->routeIs('dashboard.finance.*') ? 'active' : '' }}" href="{{ route('dashboard.finance.index') }}">
                        <i class="bi bi-pie-chart-fill link-icon"></i>
                        <span>Dashboard Keuangan</span>
                    </a>
                    @endif
                </nav>

                <!-- 2. OPERASIONAL SEWA -->
                <div class="sidebar-section-title">Operasional Sewa</div>
                <nav class="nav flex-column">
                    @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist', 'staff'))
                    <a class="sidebar-link {{ request()->routeIs('dashboard.buildings.*') ? 'active' : '' }}" href="{{ route('dashboard.buildings.index') }}">
                        <i class="bi bi-building link-icon"></i>
                        <span>Master Gedung</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.rooms.*') ? 'active' : '' }}" href="{{ route('dashboard.rooms.index') }}">
                        <i class="bi bi-door-open link-icon"></i>
                        <span>Master Kamar</span>
                    </a>
                    @endif
                    <a class="sidebar-link {{ request()->routeIs('dashboard.bookings.*') ? 'active' : '' }}" href="{{ route('dashboard.bookings.index') }}">
                        <i class="bi bi-calendar-check link-icon"></i>
                        <span>Pemesanan (Booking)</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.invoices.*') ? 'active' : '' }}" href="{{ route('dashboard.invoices.index') }}">
                        <i class="bi bi-receipt link-icon"></i>
                        <span>Tagihan (Invoice)</span>
                    </a>
                    @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist'))
                    <a class="sidebar-link {{ request()->routeIs('dashboard.payments.*') ? 'active' : '' }}" href="{{ route('dashboard.payments.index') }}">
                        <i class="bi bi-credit-card-2-front link-icon"></i>
                        <span>Verifikasi Pembayaran</span>
                    </a>
                    @endif
                    <a class="sidebar-link {{ request()->routeIs('dashboard.deposits.index', 'dashboard.deposits.show') ? 'active' : '' }}" href="{{ route('dashboard.deposits.index') }}">
                        <i class="bi bi-shield-check link-icon"></i>
                        <span>Uang Jaminan (Deposit)</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.deposits.settlements.*') ? 'active' : '' }}" href="{{ route('dashboard.deposits.settlements.index') }}">
                        <i class="bi bi-box-arrow-right link-icon"></i>
                        <span>Penyelesaian Check-Out</span>
                    </a>
                </nav>

                <!-- 3. UTILITAS & METERAN -->
                @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist', 'staff'))
                @php $isUtilitiesActive = request()->routeIs('dashboard.utilities.*'); @endphp
                <div class="sidebar-section-title">Utilitas & Meteran</div>
                <nav class="nav flex-column">
                    <a class="sidebar-link sidebar-group-toggle {{ $isUtilitiesActive ? 'active' : '' }}" 
                       data-bs-toggle="collapse" 
                       href="#submenuUtilities" 
                       role="button" 
                       aria-expanded="{{ $isUtilitiesActive ? 'true' : 'false' }}" 
                       aria-controls="submenuUtilities">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-speedometer2 link-icon"></i>
                            <span>Meter & Utilitas</span>
                        </div>
                        <i class="bi bi-chevron-right chevron"></i>
                    </a>
                    <div class="collapse {{ $isUtilitiesActive ? 'show' : '' }}" id="submenuUtilities">
                        <ul class="sidebar-submenu">
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.utilities.meters.*') ? 'active' : '' }}" href="{{ route('dashboard.utilities.meters.index') }}">
                                    <i class="bi bi-speedometer"></i>
                                    <span>Master Meter Listrik/Air</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.utilities.readings.*') ? 'active' : '' }}" href="{{ route('dashboard.utilities.readings.index') }}">
                                    <i class="bi bi-journal-check"></i>
                                    <span>Pencatatan & Review</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.utilities.tariffs.*') ? 'active' : '' }}" href="{{ route('dashboard.utilities.tariffs.index') }}">
                                    <i class="bi bi-tags"></i>
                                    <span>Pengaturan Tarif</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
                @endif

                <!-- 4. FASILITAS & LAYANAN -->
                @php 
                    $isFacilityActive = request()->routeIs('dashboard.inspections.*', 'dashboard.maintenance.*', 'dashboard.complaints.*', 'dashboard.housekeeping.*');
                @endphp
                <div class="sidebar-section-title">Fasilitas & Layanan</div>
                <nav class="nav flex-column">
                    @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist', 'staff'))
                    <a class="sidebar-link {{ request()->routeIs('dashboard.inspections.*') ? 'active' : '' }}" href="{{ route('dashboard.inspections.index') }}">
                        <i class="bi bi-clipboard2-check link-icon"></i>
                        <span>Inspeksi Kamar</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.assets.*') ? 'active' : '' }}" href="{{ route('dashboard.assets.index') }}">
                        <i class="bi bi-box-seam link-icon"></i>
                        <span>Aset & Inventaris</span>
                    </a>
                    @endif
                    <a class="sidebar-link {{ request()->routeIs('dashboard.maintenance.*') ? 'active' : '' }}" href="{{ route('dashboard.maintenance.index') }}">
                        <i class="bi bi-tools link-icon"></i>
                        <span>Perbaikan (Maintenance)</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.complaints.*') ? 'active' : '' }}" href="{{ route('dashboard.complaints.index') }}">
                        <i class="bi bi-chat-left-dots link-icon"></i>
                        <span>Keluhan Penghuni</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.housekeeping.*') ? 'active' : '' }}" href="{{ route('dashboard.housekeeping.index') }}">
                        <i class="bi bi-stars link-icon"></i>
                        <span>Kebersihan (Housekeeping)</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.announcements.*') ? 'active' : '' }}" href="{{ route('dashboard.announcements.index') }}">
                        <i class="bi bi-megaphone link-icon"></i>
                        <span>Papan Pengumuman</span>
                    </a>
                </nav>

                <!-- 5. FINANSIAL & LAPORAN -->
                @if(!auth()->user()->isTenant())
                @php 
                    $isFinanceActive = request()->routeIs('dashboard.expenses.*', 'dashboard.reports.*');
                @endphp
                <div class="sidebar-section-title">Finansial & Laporan</div>
                <nav class="nav flex-column">
                    <a class="sidebar-link {{ request()->routeIs('dashboard.expenses.index', 'dashboard.expenses.create', 'dashboard.expenses.show', 'dashboard.expenses.edit') ? 'active' : '' }}" href="{{ route('dashboard.expenses.index') }}">
                        <i class="bi bi-wallet2 link-icon"></i>
                        <span>Pengeluaran Operasional</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.expenses.categories') ? 'active' : '' }}" href="{{ route('dashboard.expenses.categories') }}">
                        <i class="bi bi-folder2 link-icon"></i>
                        <span>Kategori Pengeluaran</span>
                    </a>

                    <!-- Submenu Laporan Keuangan -->
                    <a class="sidebar-link sidebar-group-toggle {{ request()->routeIs('dashboard.reports.*') ? 'active' : '' }}" 
                       data-bs-toggle="collapse" 
                       href="#submenuReports" 
                       role="button" 
                       aria-expanded="{{ request()->routeIs('dashboard.reports.*') ? 'true' : 'false' }}" 
                       aria-controls="submenuReports">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-bar-graph link-icon"></i>
                            <span>Laporan Finansial</span>
                        </div>
                        <i class="bi bi-chevron-right chevron"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('dashboard.reports.*') ? 'show' : '' }}" id="submenuReports">
                        <ul class="sidebar-submenu">
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.reports.income') ? 'active' : '' }}" href="{{ route('dashboard.reports.income') }}">
                                    <i class="bi bi-cash-stack"></i>
                                    <span>Laporan Pendapatan</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.reports.expenses') ? 'active' : '' }}" href="{{ route('dashboard.reports.expenses') }}">
                                    <i class="bi bi-cart-x"></i>
                                    <span>Laporan Pengeluaran</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.reports.cash-flow') ? 'active' : '' }}" href="{{ route('dashboard.reports.cash-flow') }}">
                                    <i class="bi bi-arrow-left-right"></i>
                                    <span>Laporan Arus Kas</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.reports.receivables') ? 'active' : '' }}" href="{{ route('dashboard.reports.receivables') }}">
                                    <i class="bi bi-clock-history"></i>
                                    <span>Laporan Piutang & Aging</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.reports.operating-summary') ? 'active' : '' }}" href="{{ route('dashboard.reports.operating-summary') }}">
                                    <i class="bi bi-calculator"></i>
                                    <span>Ringkasan Hasil Usaha</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
                @endif

                <!-- 6. AKUNTANSI & JURNAL -->
                @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist'))
                @php 
                    $isAccountingActive = request()->routeIs('dashboard.accounting.*');
                @endphp
                <div class="sidebar-section-title">Akuntansi & Jurnal</div>
                <nav class="nav flex-column">
                    <a class="sidebar-link {{ request()->routeIs('dashboard.accounting.coa') ? 'active' : '' }}" href="{{ route('dashboard.accounting.coa') }}">
                        <i class="bi bi-diagram-3 link-icon"></i>
                        <span>Bagan Akun (COA)</span>
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('dashboard.accounting.journals.*') ? 'active' : '' }}" href="{{ route('dashboard.accounting.journals.index') }}">
                        <i class="bi bi-journal-text link-icon"></i>
                        <span>Jurnal Umum</span>
                    </a>

                    <!-- Submenu Laporan Akuntansi -->
                    <a class="sidebar-link sidebar-group-toggle {{ request()->routeIs('dashboard.accounting.reports.*') ? 'active' : '' }}" 
                       data-bs-toggle="collapse" 
                       href="#submenuAccountingReports" 
                       role="button" 
                       aria-expanded="{{ request()->routeIs('dashboard.accounting.reports.*') ? 'true' : 'false' }}" 
                       aria-controls="submenuAccountingReports">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-bank link-icon"></i>
                            <span>Laporan Keuangan</span>
                        </div>
                        <i class="bi bi-chevron-right chevron"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('dashboard.accounting.reports.*') ? 'show' : '' }}" id="submenuAccountingReports">
                        <ul class="sidebar-submenu">
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.accounting.reports.trial-balance') ? 'active' : '' }}" href="{{ route('dashboard.accounting.reports.trial-balance') }}">
                                    <i class="bi bi-scale"></i>
                                    <span>Neraca Saldo</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.accounting.reports.income-statement') ? 'active' : '' }}" href="{{ route('dashboard.accounting.reports.income-statement') }}">
                                    <i class="bi bi-graph-up-arrow"></i>
                                    <span>Laba Rugi</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.accounting.reports.balance-sheet') ? 'active' : '' }}" href="{{ route('dashboard.accounting.reports.balance-sheet') }}">
                                    <i class="bi bi-safe2"></i>
                                    <span>Neraca Keuangan</span>
                                </a>
                            </li>
                            <li class="sidebar-subitem">
                                <a class="sidebar-sublink {{ request()->routeIs('dashboard.accounting.reports.reconciliation') ? 'active' : '' }}" href="{{ route('dashboard.accounting.reports.reconciliation') }}">
                                    <i class="bi bi-check2-square"></i>
                                    <span>Rekonsiliasi Subledger</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
                @endif
            </div>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="sidebar-user-pill">
                        <div class="sidebar-avatar">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div class="sidebar-user-info">
                            <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                            <div class="sidebar-user-role">{{ auth()->user()->role->label() }}</div>
                        </div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="d-inline m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm p-1 text-danger border-0" title="Keluar dari sistem">
                            <i class="bi bi-box-arrow-right fs-5"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        @endauth

        <!-- MAIN VIEW (Topbar + Content Area) -->
        <div class="app-main">
            @auth
            <!-- Minimalist Topbar -->
            <header class="app-topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="topbar-toggle-btn" id="sidebarToggleBtn" type="button" title="Buka/Tutup Sidebar" aria-label="Toggle Sidebar">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div class="d-none d-sm-block">
                        <h1 class="topbar-title">@yield('title', 'SIM Kos & Guesthouse')</h1>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="text-end d-none d-md-block">
                        <div class="fw-semibold text-dark small">{{ auth()->user()->name }}</div>
                        <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">{{ auth()->user()->role->label() }}</span>
                    </div>

                    <!-- User Actions Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-light border btn-sm d-flex align-items-center gap-2 rounded-pill px-2 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="sidebar-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                            <i class="bi bi-chevron-down text-muted small pe-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2" style="min-width: 200px;">
                            <li class="px-3 py-1 border-bottom mb-2">
                                <div class="fw-bold small text-dark">{{ auth()->user()->name }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ auth()->user()->email }}</div>
                                <span class="badge bg-teal-subtle text-primary-teal border border-teal-subtle mt-1" style="font-size: 0.7rem;">
                                    {{ auth()->user()->role->label() }}
                                </span>
                            </li>
                            <li>
                                <a class="dropdown-item small d-flex align-items-center gap-2" href="{{ route('dashboard.index') }}">
                                    <i class="bi bi-grid text-muted"></i> Dashboard Utama
                                </a>
                            </li>
                            @if(!auth()->user()->isTenant())
                            <li>
                                <a class="dropdown-item small d-flex align-items-center gap-2" href="{{ route('dashboard.finance.index') }}">
                                    <i class="bi bi-pie-chart text-muted"></i> Dashboard Keuangan
                                </a>
                            </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item small text-danger d-flex align-items-center gap-2">
                                        <i class="bi bi-box-arrow-right"></i> Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            @endauth

            <!-- Content Area -->
            <main class="app-content">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0" role="alert">
                        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                        <div>{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                        <div>{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 text-warning"></i>
                        <div>{{ session('warning') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 text-info"></i>
                        <div>{{ session('info') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar Responsive Toggle & Drawer Utilities Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const appLayout = document.getElementById('appLayout');
            const sidebarEl = document.getElementById('sidebarMenu');

            if (toggleBtn && appLayout && sidebarEl) {
                // Restore desktop collapse preference from localStorage
                if (window.innerWidth >= 992) {
                    const isCollapsed = localStorage.getItem('simkos_sidebar_collapsed') === 'true';
                    if (isCollapsed) {
                        appLayout.classList.add('sidebar-collapsed');
                    }
                }

                toggleBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (window.innerWidth >= 992) {
                        // Desktop: toggle slide collapse
                        appLayout.classList.toggle('sidebar-collapsed');
                        localStorage.setItem('simkos_sidebar_collapsed', appLayout.classList.contains('sidebar-collapsed'));
                    } else {
                        // Mobile/Tablet: toggle Bootstrap Offcanvas
                        const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(sidebarEl);
                        bsOffcanvas.toggle();
                    }
                });

                // Auto-close offcanvas on mobile when clicking a non-collapsible link
                sidebarEl.querySelectorAll('.sidebar-link:not(.sidebar-group-toggle), .sidebar-sublink').forEach(function (link) {
                    link.addEventListener('click', function () {
                        if (window.innerWidth < 992) {
                            const bsOffcanvas = bootstrap.Offcanvas.getInstance(sidebarEl);
                            if (bsOffcanvas) {
                                bsOffcanvas.hide();
                            }
                        }
                    });
                });
            }

            // Global Anti-Double Submission Protection for Forms
            document.addEventListener('submit', function (e) {
                const form = e.target;
                if (!form.checkValidity || form.checkValidity()) {
                    const submitBtns = form.querySelectorAll('button[type="submit"]');
                    submitBtns.forEach(btn => {
                        if (!btn.disabled && !btn.classList.contains('no-disable-on-submit')) {
                            setTimeout(() => {
                                btn.disabled = true;
                                if (!btn.hasAttribute('data-original-html')) {
                                    btn.setAttribute('data-original-html', btn.innerHTML);
                                }
                                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses...';
                            }, 20);
                        }
                    });
                }
            });
        });
    </script>

    @if(isset($errors) && $errors->any() && old('_drawer_id'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const targetDrawerId = "{{ old('_drawer_id') }}";
            const targetDrawerEl = document.getElementById(targetDrawerId);
            if (targetDrawerEl) {
                const drawerInstance = bootstrap.Offcanvas.getOrCreateInstance(targetDrawerEl);
                drawerInstance.show();
            }
        });
    </script>
    @endif
    @stack('scripts')
</body>
</html>
