<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Katalog Kamar & Pemesanan') — SIM Kos & Guesthouse</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-public {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .navbar-brand-badge {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #fff;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-weight: 700;
        }
        .btn-teal {
            background-color: #0d9488;
            border-color: #0d9488;
            color: #fff;
            font-weight: 500;
        }
        .btn-teal:hover {
            background-color: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        .btn-outline-teal {
            border-color: #0d9488;
            color: #0d9488;
            font-weight: 500;
        }
        .btn-outline-teal:hover {
            background-color: #0d9488;
            color: #fff;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            background: #fff;
        }
        .card-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.08);
        }
        .badge-available {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 600;
        }
        .badge-dp {
            background-color: #e0e7ff;
            color: #4338ca;
            font-weight: 600;
        }
        .footer-public {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            margin-top: auto;
            padding: 1.5rem 0;
            color: #64748b;
            font-size: 0.875rem;
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Navbar Publik -->
    <header class="navbar navbar-expand-lg navbar-public sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark text-decoration-none" href="{{ route('public.catalog') }}">
                <span class="navbar-brand-badge">
                    <i class="bi bi-houses-fill"></i>
                </span>
                <span>SIM Kos & Guesthouse</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPublicContent" aria-controls="navPublicContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navPublicContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('public.catalog') ? 'active fw-semibold text-teal' : 'text-secondary' }}" href="{{ route('public.catalog') }}">
                            <i class="bi bi-grid me-1"></i> Katalog Kamar
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <form action="#" method="GET" class="d-flex gap-1" onsubmit="event.preventDefault(); trackWithPrompt();">
                        <input type="text" id="trackTokenInput" class="form-control form-control-sm" placeholder="Nomor Token Pelacakan..." style="width: 200px;">
                        <button type="button" onclick="trackWithPrompt()" class="btn btn-sm btn-outline-secondary" title="Lacak Pemesanan">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                    <a href="{{ route('login') }}" class="btn btn-sm btn-teal ms-2">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Staf / Penyewa
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Alerts -->
    <div class="container mt-3">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif
    </div>

    <!-- Main Content -->
    <main class="py-4">
        @yield('content')
    </main>

    <!-- Footer Publik -->
    <footer class="footer-public">
        <div class="container d-flex flex-wrap justify-content-between align-items-center">
            <div>
                &copy; {{ date('Y') }} SIM Kos & Guesthouse. Sistem Manajemen Kamar & Booking Terintegrasi.
            </div>
            <div class="d-flex gap-3">
                <a href="{{ route('public.catalog') }}" class="text-secondary text-decoration-none">Katalog</a>
                <a href="{{ route('login') }}" class="text-secondary text-decoration-none">Portal Staf</a>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function trackWithPrompt() {
            var val = document.getElementById('trackTokenInput').value.trim();
            if (val) {
                window.location.href = "{{ url('/booking/track') }}/" + encodeURIComponent(val);
            } else {
                alert('Silakan masukkan access token pemesanan Anda.');
            }
        }
    </script>
    @stack('scripts')
</body>
</html>
