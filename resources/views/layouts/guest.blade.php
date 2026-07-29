<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Portal arsip publik {{ config('app.pencipta_arsip') }}">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('fonts/circular/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('fonts/fontawesome/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/guest-portal.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mfa-code-input.min.css') }}">
    @stack('css')
    <title>{{ $judul }} — Portal Arsip Publik</title>
</head>

<body class="guest-portal-body">
    @include('sweetalert::alert')

    <header class="guest-navbar">
        <nav class="navbar navbar-expand-lg navbar-light p-0">
            <div class="container">
                <a class="guest-brand" href="{{ route('guest') }}" aria-label="Beranda Portal Arsip Publik">
                    <span class="guest-brand-mark"><i class="fas fa-archive" aria-hidden="true"></i></span>
                    <span class="guest-brand-copy">
                        <strong>APSM</strong>
                        <small>Portal Arsip Publik</small>
                    </span>
                </a>

                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#guestNavbar"
                    aria-controls="guestNavbar" aria-expanded="false" aria-label="Buka navigasi">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="guestNavbar">
                    <ul class="navbar-nav ml-auto align-items-lg-center">
                        <li class="nav-item">
                            <a href="{{ route('guest') }}"
                                class="nav-link {{ request()->routeIs('guest') ? 'active' : '' }}">Beranda</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('guest.masuk') }}"
                                class="nav-link {{ request()->routeIs('guest.masuk') ? 'active' : '' }}">Surat Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('guest.keluar') }}"
                                class="nav-link {{ request()->routeIs('guest.keluar') ? 'active' : '' }}">Surat Keluar</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('guest.digital') }}"
                                class="nav-link {{ request()->routeIs('guest.digital') ? 'active' : '' }}">Surat Digital</a>
                        </li>
                        <li class="nav-item ml-lg-2">
                            <a href="{{ url('/') }}" class="nav-link guest-login-link">
                                <i class="fas fa-lock mr-1" aria-hidden="true"></i> Masuk Admin
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="guest-main">
        @yield('konten')
    </main>

    <footer class="guest-footer">
        <div class="container guest-footer-inner">
            <span><strong>APSM</strong> · Aplikasi Pengelolaan Surat Menyurat</span>
            <span>&copy; {{ date('Y') }} {{ config('app.pencipta_arsip') }}</span>
        </div>
    </footer>

    <script src="{{ asset('js/jquery-3.5.1.min.js') }}"></script>
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/mfa-code-input.min.js') }}"></script>
    @stack('js')
</body>

</html>
