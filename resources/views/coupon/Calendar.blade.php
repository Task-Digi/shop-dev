<!-- resources/views/calendar.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Black Friday Calendar</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .calendar-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            margin: 5px;
            cursor: pointer;
        }

        .coupon-image {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
        }

        .calendar-box:hover .coupon-image {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Black Friday Offer Calendar</h2>
        <div class="row">
            @for ($i = 1; $i <= 24; $i++)
                <div class="col-md-3">
                    <div class="calendar-box">
                        <h4>{{ \Carbon\Carbon::parse($dates[$i-1])->format('M d') }}</h4>
                        <p>Black Friday Offer</p>
                        <div class="coupon-image">
                            <img src="{{ asset("images/c{$i}.png") }}" alt="Coupon Image {{ $i }}">
                        </div>
                    </div>
                </div>
            @endfor
        </div>
    </div>
</body>
</html>
