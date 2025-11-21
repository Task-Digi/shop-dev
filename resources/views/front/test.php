<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>QUESTIFY</title>
    <link rel="stylesheet" src="{{asset('assets/css/bootstrap.min.css')}}"></link>
    <link rel="stylesheet" src="{{asset('assets/css/bootstrap-slider.min.css')}}"></link>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
          integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">

    <style>
        #ex1Slider .slider-selection {
            background: #BABABA;
        }

        .ques .card {
            border: 2px solid #002a71 !important;
        }

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

        .ButtonClickedgreen {
            background-color: rgb(6, 78, 6);
        }

        .ButtonClickedred {
            background-color: rgb(119, 2, 2);
        }

        .ButtonClickedyellow {
            background-color: rgb(146, 120, 1);
        }
    </style>
    @stack('styles')
</head>
<body>
<div id="app">
    @yield('content')
</div>
@stack('scripts')
<script>
    function questionsucess(e) {
        var questionsuccess = $(e).attr('data-child');
        $('[data-child=' + questionsuccess + ']').addClass('ButtonClickedgreen');
        $(e).next().removeClass('ButtonClickedred');
        $(e).next().next().removeClass('ButtonClickedyellow');
    }

    function questiondanger(e) {
        var questiondanger = $(e).attr('data-child');
        $('[data-child=' + questiondanger + ']').addClass('ButtonClickedred');
        $(e).prev().removeClass('ButtonClickedgreen');
        $(e).next().removeClass('ButtonClickedyellow');
        console.log($(e).prev());
    }

    function questionwarning(e) {
        var questionwarning = $(e).attr('data-child');
        $('[data-child=' + questionwarning + ']').addClass('ButtonClickedyellow');
        $(e).prev().removeClass('ButtonClickedred');
        $(e).prev().prev().removeClass('ButtonClickedgreen');
        console.log($(e).first());
    }
</script>
<script src="{{asset('assets/js/jquery-3.5.1.min.js')}}"></script>
<script src="{{asset('assets/js/bootstrap-slider.min.js')}}"></script>
</body>
</html>
