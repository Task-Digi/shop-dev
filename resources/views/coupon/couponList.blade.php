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
            font-size: 16px;
            text-decoration: none;
        }
        
        .table {
            text-align: center;
            overflow-x: auto; /* Add horizontal scroll on small screens */
        }

        @media (max-width: 576px) {
            .table th, .table td {
                font-size: 12px;
            }

            .couponbtn {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Display Coupon List -->
        <h2>Coupon List
        <a class="couponbtn" href="{{ route('coupons.create') }}">Create Coupon</a>
        </h2>

         @if(isset($coupons) && count($coupons) > 0)
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mobile Number</th>
                            <th>Voucher(s)</th>
                            <th>End Date</th>
                            <th>Used</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coupons->groupBy('mobile_nr') as $mobile => $mobileCoupons)
                            <tr>
                                <td>{{ $mobile }}</td>
                                <td>{{ implode(', ',  $mobileCoupons->sortBy('voucher')->pluck('voucher')->toArray()) }}</td>
                                <!--<td>{{ \Carbon\Carbon::parse($mobileCoupons->first()->end_date)->format('Y-m-d') }}</td>-->
                                <td>
                                    @foreach($mobileCoupons->sortBy('voucher') as $coupon)
                                        {{ $coupon->voucher }} ({{ \Carbon\Carbon::parse($coupon->end_date)->format('Y-m-d') }}),
                                    @endforeach
                                </td>
                                <td>{{ $mobileCoupons->first()->used ? 'Yes' : 'No' }}</td>
                                <td>
                                    <a href="{{ route('coupons.edit', $mobileCoupons->first()->id) }}">Edit</a>
                                    <button onclick="confirmDelete({{ $mobileCoupons->first()->id }})">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        function confirmDelete(id) {
            var result = confirm("Are you sure you want to delete this coupon?");
            if (result) {
                window.location.reload();
            }
        }
    </script>
</body>
</html>
