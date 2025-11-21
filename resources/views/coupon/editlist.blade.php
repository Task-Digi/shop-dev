<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* You can keep any custom CSS here if needed. */
        .container {
            text-align: center;
            padding: 10px;
        }

        .couponbtn {
            font-size: 18px;
            text-decoration: none;
        }

        .coupon-input {
            margin-bottom: 10px;
        }

    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Coupon
            <a class="couponbtn" href="{{ route('coupons.couponList') }}">Back</a>
        </h2>

        <form method="POST" action="{{ route('coupons.update', $coupon->id) }}">
        
            @csrf
            @method('PUT')

            <label for="mobile_nr">Mobile Number:</label>
            <input type="text" name="mobile_nr" value="{{ $coupon->mobile_nr }}" required readonly><br>

            <label for="voucher">Coupon Number:</label>
            <input type="text" name="voucher" value="{{ $coupon->voucher }}" required><br>

            <label for="end_date">Expiry Date:</label>
            <input type="date" name="end_date" value="{{ $coupon->end_date }}" required><br>

            <button type="submit">Update Coupon</button>
        </form>
    </div>
</body>
</html>
