<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="google-site-verification" content="idGSZ-IW8l_iuSFuZvTL2WihbyUKJ_-wVP51xapXHFM" />

    <title>SHOP</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
          integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
          integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/11.0.2/css/bootstrap-slider.css" integrity="sha512-SZgE3m1he0aEF3tIxxnz/3mXu/u/wlMNxQSnE0Cni9j/O8Gs+TjM9tm1NX34nRQ7GiLwUEzwuE3Wv2FLz2667w==" crossorigin="anonymous" />
    <link rel="stylesheet" href="{{asset('assets/css/Chart.min.css')}}">
    <!-- Modern UI Styles -->
    <link rel="stylesheet" href="{{asset('assets/css/modern-ui.css')}}?v=1">
    <link rel="stylesheet" href="{{asset('assets/css/template.css')}}?version=07092021&v3">
    <style>
        #ex1Slider .slider-selection {
            background: #BABABA;
        }

        /*.ques .card {*/
        /*    border: 2px solid #002a71 !important;*/
        /*}*/

        .check {
            -webkit-appearance: none; /*hides the default checkbox*/
            height: 20px;
            width: 20px;
            position: relative;
            top: 20px;
            left: 20px;
            transition: 0.10s;
            background-color: #FE0006;
            text-align: center;
            font-weight: 600;
            color: white;
            border-radius: 3px;
            outline: none;
        }

        .check:checked {
            background-color: #0E9700;
        }

        .check:before {
            content: "✖";
        }

        .check:checked:before {
            content: "✔";
        }

        .check:hover {
            cursor: pointer;
            opacity: 0.8;
        }

        .fa-3x {
            font-size: 28px !important;
        }

        button {
            margin: 2px;
        }
    </style>
    @stack('styles')
</head>
<body>
    {{-- <h1>hi</h1> --}}
    <div id="app">
        @yield('content')
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <script src="{{asset('assets/js/bootstrap-slider.js')}}" ></script>
    <script src="{{asset('assets/js/Chart.min.js')}}" ></script>
    <script src="{{asset('assets/js/front-app.js')}}?version=07092021&v1" ></script>
    <script>

    </script>
    @stack('scripts')
</body>
</html>
