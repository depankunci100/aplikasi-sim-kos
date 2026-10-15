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
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        .navbar-brand {
            font-weight: 700;
            color: #0f766e !important;
            letter-spacing: -0.5px;
        }
        .bg-primary-teal {
            background-color: #0f766e !important;
        }
        .text-primary-teal {
            color: #0f766e !important;
        }
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
        .nav-link {
            font-weight: 500;
            color: #475569;
            padding: 0.5rem 0.85rem;
            border-radius: 6px;
        }
        .nav-link:hover, .nav-link.active {
            color: #0f766e;
            background-color: #f0fdfa;
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
        .badge-status-unpaid { background-color: #ef4444; color: #fff; }
        .badge-status-partially_paid { background-color: #f59e0b; color: #fff; }
        .badge-status-paid { background-color: #10b981; color: #fff; }
        .badge-status-pending { background-color: #f59e0b; color: #fff; }
        .badge-status-verified { background-color: #10b981; color: #fff; }
        .badge-status-rejected { background-color: #64748b; color: #fff; }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-2">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard.index') }}">
                <i class="bi bi-buildings-fill fs-4 text-primary-teal"></i>
                <span>SIM Kos & Guesthouse</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                @auth
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-1">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">
                            <i class="bi bi-grid-fill me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard.payments.*') ? 'active' : '' }}" href="{{ route('dashboard.payments.index') }}">
                            <i class="bi bi-credit-card-2-front me-1"></i> Verifikasi Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard.invoices.*') ? 'active' : '' }}" href="{{ route('dashboard.invoices.index') }}">
                            <i class="bi bi-receipt me-1"></i> Tagihan (Invoice)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard.inspections.*') ? 'active' : '' }}" href="{{ route('dashboard.inspections.index') }}">
                            <i class="bi bi-clipboard2-check me-1"></i> Inspeksi Kamar
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('dashboard.utilities.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-speedometer2 me-1"></i> Utilitas & Meter
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('dashboard.utilities.meters.index') }}"><i class="bi bi-speedometer me-2"></i>Daftar Meter Listrik/Air</a></li>
                            <li><a class="dropdown-item" href="{{ route('dashboard.utilities.readings.index') }}"><i class="bi bi-journal-check me-2"></i>Pencatatan & Review</a></li>
                            <li><a class="dropdown-item" href="{{ route('dashboard.utilities.tariffs.index') }}"><i class="bi bi-tags me-2"></i>Pengaturan Tarif</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end d-none d-sm-block">
                        <div class="fw-semibold text-dark">{{ auth()->user()->name }}</div>
                        <span class="badge bg-light text-dark border">{{ auth()->user()->role->label() }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1" title="Keluar">
                            <i class="bi bi-box-arrow-right"></i> Keluar
                        </button>
                    </form>
                </div>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="py-4">
        <div class="container-fluid px-4">
            <!-- Flash Message Alerts -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>{{ session('error') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5"></i>
                    <div>{{ session('warning') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>{{ session('info') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
