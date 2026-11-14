<!doctype html>
<html lang="id">
<head>
<link rel="icon" type="image/png" href="{{ asset('public/images/vina-estetica-logo.png') }}?v=20261113">
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#071f2d">
<title>Accounting RSUVE</title>
<link rel="stylesheet" href="{{ asset('public/css/accounting.css') }}?v=20261025-perf-1">
<link rel="stylesheet" href="{{ asset('public/css/form-controls.css') }}?v=20261025-1">
<link rel="stylesheet" href="{{ asset('public/css/usability.css') }}?v=20261029-2">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <img src="{{ asset('public/images/vina-estetica-logo.png') }}" alt="Logo RS. Vina Estetica">
            <div><h1>{{ config('accounting.hospital_name','RS. Vina Estetica') }}</h1><p>Accounting System</p></div>
        </div>
        <h2 style="margin:0 0 6px;font-size:21px">Selamat datang</h2>
        <p class="muted" style="margin:0 0 23px">Masuk untuk mengakses sistem accounting rumah sakit.</p>
        @if(request()->boolean('expired'))<div class="error" role="status">Sesi berakhir setelah 2 jam tanpa aktivitas. Silakan masuk kembali.</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}">@csrf
            <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" placeholder="nama@rumahsakit.com" required autofocus></div>
            <div class="field"><label>Password</label><input type="password" name="password" placeholder="Masukkan password" required></div>
            <div style="margin:16px 0">
                <label for="remember-login" style="display:flex;align-items:center;gap:10px;cursor:pointer"><input id="remember-login" type="checkbox" name="remember" value="1" @checked(old('remember')) style="width:18px;height:18px;margin:0">Tetap masuk sampai saya logout</label>
                <small class="muted">Tanpa centang, sesi berakhir setelah 2 jam tidak ada aktivitas.</small>
            </div>
            <button class="btn primary login-submit" type="submit">Login</button>
        </form>
        <div class="login-foot">© {{ date('Y') }} {{ config('accounting.hospital_name','RS. Vina Estetica') }} · Accounting System</div>
    </div>
</div>
<script src="{{ asset('public/js/accounting-ui.js') }}?v=20261025-perf-1"></script>
<script src="{{ asset('public/js/form-controls.js') }}?v=20261113-perf-2"></script>
</body>
</html>
