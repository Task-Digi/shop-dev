<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>QUESTIFY</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    </head>
    <body>
        <div class="container">
            <center>
                <div class="col-md-6 mt-5">
                    <div class="card" style="border: 1px solid rgb(0 0 0);">
                        @if(isset($access))
                        <div style="background: #002a71;" class="card-header">
                            <h4 class="text-light">@if(isset($access)){{strtoupper(config('settings.customersName')[$access->Access_Customer_ID])}}@endif</h4>
                        </div>
                        <div class="card-body">
                             <h1 class="card-title">TUSEN</h1>
                            <h1 class="card-title">TAKK !</h1>
                        </div>
                            @php
                                \Illuminate\Support\Facades\Session::forget('access');
                                \Illuminate\Support\Facades\Session::forget('respond');
                            @endphp
                        @endif
                    </div>
                </div>
            </center>
        </div>
    </body>
</html>
