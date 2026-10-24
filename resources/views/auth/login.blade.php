<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#071f2d">
<title>Login Accounting | {{ config('accounting.hospital_name','RS. Vina Estetica') }}</title>
<link rel="stylesheet" href="{{ asset('public/css/accounting.css') }}">
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
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}">@csrf
            <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" placeholder="nama@rumahsakit.com" required autofocus></div>
            <div class="field"><label>Password</label><input type="password" name="password" placeholder="Masukkan password" required></div>
            <button class="btn primary login-submit" type="submit">Login</button>
        </form>
        <div class="login-foot">© {{ date('Y') }} {{ config('accounting.hospital_name','RS. Vina Estetica') }} · Accounting System</div>
    </div>
</div>
<script src="{{ asset('public/js/accounting-ui.js') }}?v=20260819-design-1"></script>
</body>
</html>
