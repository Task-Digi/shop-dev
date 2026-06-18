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

        @media (max-width: 576px) {
            .coupon-input {
                padding: 10px; /* Adjust padding for better spacing on smaller screens */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Coupon
            <a class="couponbtn" href="{{ route('coupons.couponList') }}">Back</a>
        </h2>

        <!-- Form for editing coupons -->
        <form method="POST" action="{{ route('coupons.updateBulk') }}">
            @csrf
            @method('PUT')
        
            <div class="form-group">
                <label for="mobile_nr">Mobile Number:</label>
                <input type="text" class="form-control" name="mobile_nr" value="{{ $coupon->mobile_nr }}" required readonly>
            </div>
        
            <!-- Coupon Numbers Edit Boxes -->
            @foreach($coupons as $associatedCoupon)
                <div class="form-group coupon-input">
                    <label for="voucher[]">Coupon Number:</label>
                    <input type="text" class="form-control" name="voucher[]" value="{{ $associatedCoupon->voucher }}" required>
                </div>
                <!--<div class="form-group coupon-input">-->
                <!--    <label for="end_date[]">Expiry Date:</label>-->
                <!--    <input type="date" class="form-control" name="end_date[]" value="{{ $associatedCoupon->end_date }}" required>-->
                <!--</div>-->
                <div class="form-group coupon-input">
                <label for="end_date[]">Expiry Date:</label>
                <input type="date" class="form-control" name="end_date[]" value="{{ old('end_date[]', $associatedCoupon->end_date) }}" required>
                </div>

            @endforeach
        
            <button type="submit" class="btn btn-primary">Update Coupons</button>
        </form>
    </div>
</body>
</html>
