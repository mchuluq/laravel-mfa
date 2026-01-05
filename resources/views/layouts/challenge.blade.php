<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="content-type" content="text/html" charset="UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="{{config('app.name')}}">
    <meta name="app_name" content="{{config('app.name')}}">
    <meta name="app_version" content="{{config('app.version','dev')}}">
    <meta name="theme-color" content="#a82323" />
    <meta name="google" content="notranslate"/>

    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <base href="{{env('APP_URL')}}">

    <link rel="icon" href="{{url('assets/img/favicon.ico')}}">
    <link rel="apple-touch-icon" href="{{url('assets/img/sister.yudharta.1024.webp')}}">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') | {{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('assets/css/app-base.css') }}" rel="stylesheet">
    
    @stack('styles')
</head>
<body>
    <div class="c-auth-layout flex-row align-items-center" id="app">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <!-- Logo -->
                    <div class="d-flex w-100 justify-content-center">
                        <a href="/">
                            <img class="mb-3" src="/assets/img/yudharta.logo.png" alt="" width="64" height="64">
                        </a>
                    </div>

                    <!-- Alert Messages -->
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if(session('message'))
                        <div class="alert alert-primary">
                            {{ session('message') }}
                        </div>
                    @endif

                    @yield('alert')

                    <!-- Main Content -->
                    @yield('content')

                    <!-- Cancel Link -->
                    <div class="text-center mt-3">
                        <small class="text-muted">Ada kendala ? <a href="#" onclick="event.preventDefault(); document.getElementById('cancel-form').submit();">Batal & Keluar</a></small>
                        <form id="cancel-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="{{ asset('assets/js/app-base.js') }}"></script>
    @stack('scripts')
</body>
</html>