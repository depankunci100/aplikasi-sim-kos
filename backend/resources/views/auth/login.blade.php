<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - SIM Kos & Guesthouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f766e 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 440px;
            width: 100%;
        }
        .btn-primary-teal {
            background-color: #0f766e;
            border-color: #0f766e;
            color: #fff;
            padding: 0.65rem 1rem;
            font-weight: 600;
        }
        .btn-primary-teal:hover {
            background-color: #115e59;
            border-color: #115e59;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="container p-3">
    <div class="card login-card mx-auto bg-white p-4 p-sm-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-light text-teal rounded-circle p-3 mb-2" style="color: #0f766e;">
                <i class="bi bi-buildings-fill fs-2"></i>
            </div>
            <h4 class="fw-bold mb-1">SIM Kos & Guesthouse</h4>
            <p class="text-muted small">Portal Masuk Manajemen Operasional & Kasir</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success py-2 small" role="alert">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold">Email atau No. Handphone</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                    <input type="text" name="email" id="email" class="form-control" value="{{ old('email', 'owner@simkos.id') }}" required autofocus placeholder="nama@simkos.id">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" value="Password123!" required placeholder="••••••••">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" checked>
                    <label class="form-check-label small text-muted" for="remember">Ingat Saya</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-teal w-100 rounded-3 mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Dashboard
            </button>
        </form>

        <hr class="my-3 text-muted">

        <!-- Demo Accounts Quick Fill -->
        <div class="small text-muted text-center mb-2 fw-semibold">Akun Pengujian Cepat:</div>
        <div class="d-grid gap-2 d-sm-flex justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDemo('owner@simkos.id')">Owner</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDemo('resepsionis@simkos.id')">Kasir</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDemo('manager@simkos.id')">Manager</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDemo('staff@simkos.id')">Staff</button>
        </div>
    </div>
</div>

<script>
    function setDemo(email) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = 'Password123!';
    }
</script>
</body>
</html>
