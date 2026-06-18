<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        /* You can keep any custom CSS here if needed. */
        .container {
            padding: 10px;
        }

        .edit-option {
            padding: 10px;
        }

        .couponbtn {
            font-size: 18px;
            text-decoration: none;
        }
          
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
        }
    
        @media (max-width: 576px) {
            /* Adjust the styles for smaller screens */
            .table th, .table td {
                font-size: 12px;
                padding: 8px;
            }
    
            .btn-sm {
                padding: 5px 10px;
                font-size: 12px;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center">Create Coupon
            <a class="couponbtn" href="{{ route('coupons.couponList') }}">Coupon List</a>
        </h2>
        <div class="mb-4">
            <form method="POST" action="{{ route('coupons.store') }}" id="couponForm">
                @csrf
                <div class="form-group">
                    <label for="mobile_nr">Mobile Number:</label>
                    <input type="text" class="form-control" name="mobile_nr" id="mobile_nr" required oninput="checkMobileNumber()">
                    <span id="mobile_status"></span>
                    <div id="existing_coupons"></div>
                </div>
                @for ($i = 1; $i <= 5; $i++)
                    <div class="form-group">
                        <label for="voucher{{ $i }}">Voucher {{ $i }}:</label>
                        <input type="checkbox" name="voucher{{ $i }}">
                        <br>
                        <label for="end_date{{ $i }}">Expiry Date:</label>
                        <input type="date" class="form-control" name="end_date{{ $i }}" required>
                    </div>
                @endfor
                <button type="submit" class="btn btn-primary">Create Coupon</button>
            </form>
        </div>
    </div>

      <div class="edit-option">
        @if(isset($coupons) && count($coupons) > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Mobile Number</th>
                            <th>Voucher</th>
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
                                <td>
                                    @foreach($mobileCoupons->sortBy('voucher') as $coupon)
                                        {{ $coupon->voucher }} ({{ \Carbon\Carbon::parse($coupon->end_date)->format('Y-m-d') }}),
                                    @endforeach
                                </td>
                                <td>{{ $mobileCoupons->first()->used ? 'Yes' : 'No' }}</td>
                                <td>
                                    <a href="{{ route('coupons.edit', $mobileCoupons->first()->id) }}" class="btn btn-info btn-sm">Edit</a>
                                    <form method="POST" action="{{ url('coupons/delete/' . $mobileCoupons->first()->id) }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script>
        // Hide the edit-option initially
        $(document).ready(function () {
            $('.edit-option').hide();
        });

        function checkMobileNumber() {
            var mobileNumber = $('#mobile_nr').val();

            // Check if the mobile number is empty
            if (mobileNumber.trim() === '') {
                // Mobile number is empty, hide the table
                $('.edit-option').hide();
                return;
            }

            // Make an AJAX request to check if the mobile number exists in the database
            $.ajax({
                type: 'GET',
                url: '/check-mobile-number/' + mobileNumber, // Replace with your actual route
                success: function (data) {
                    if (data.exists) {
                        // Mobile number exists, show the table and update content
                        $('.edit-option').show();
                        $('.edit-option').html(data.details);
                    } else {
                        // Mobile number does not exist, hide the table
                        $('.edit-option').hide();
                    }
                }
            });
        }
    </script>
</body>
</html>
