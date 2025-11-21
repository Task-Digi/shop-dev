<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<style>
    .center-box {
        text-align: center;
        border: solid;
        padding: 20px;
        margin: 10px;
       
    }

    .center-box img {
        max-width: 100%;
        height: auto;
    }

    /* Set background color based on the $backgroundColor variable */
    .center-box.used {
        background-color: lightblue;
    }

    .center-box:not(.used) {
        background-color: white;
    }
    .row{
        display:inline;
    }
    .coupon_num{
        position: absolute; 
        top: 42%; 
        left: 78%; 
        transform: translate(-50%, -50%); 
        z-index: 1;
        font-size:84px;
        font-weight:bold;
        color:white;
    }
    .used-mobile-number{
        text-align: center;
    }
    
    /* Create three equal columns that floats next to each other */
    .column {
      float: left;
      width: 33.33%;
      padding: 10px;
      height: 250px; /* Should be removed. Only for demonstration */
    }
    
    /* Clear floats after the columns */
    .row:after {
      content: "";
      display: table;
      clear: both;
    }
    
    .image-container {
        text-align: center;
        margin: 20px;
    }

    .image-container img {
        max-width: 100%;
        height: auto;
        margin: 20px;
    }
    
    .offer-text {
    text-align: center;
    margin-bottom: 20px;
    }
    
    /* Additional styles for mobile responsiveness */
    /*@media (min-width: 150px) {*/
    /*    .mb-md-4 {*/
    /*        margin-bottom: 0; */
    /*    }*/
    /*}*/
    
    /* Two Boxes with Store Information */
    .mb-4.row {
        display: flex; /* Use flexbox to make the columns appear side by side */
        flex-wrap: wrap; /* Allow flex items to wrap to the next line on smaller screens */
        margin-right: -15px; /* Counteract Bootstrap's default gutter spacing */
        margin-left: -15px; /* Counteract Bootstrap's default gutter spacing */
    }
    
    .col-md-6.mb-4.store-info-box {
        flex: 0 0 250px;
        padding-right: 15px; /* Counteract Bootstrap's default gutter spacing */
        padding-left: 15px; /* Counteract Bootstrap's default gutter spacing */
    }
    
    /* Fargerike Alnabru */
    .col-md-6.mb-4.store-info-box {
        flex: 0 0 250px;
        padding-right: 15px; /* Counteract Bootstrap's default gutter spacing */
        padding-left: 15px; /* Counteract Bootstrap's default gutter spacing */
    }
    
    /* Additional styles for mobile responsiveness */
    /*@media (max-width: 300px) {*/
    /*    .mb-4.row {*/
    /*        flex-direction: column; */
    /*    }*/
    /*}*/

</style>

<body>
            <div class="container">
                <div class="image-container">
                    <!-- Image 1 -->
                    <img src="{{ asset('images/FABlogo.jpg') }}" alt="Image 1" class="img-fluid">
                    <!-- Image 2 -->
                    <img src="{{ asset('images/FMBlogo.png') }}" alt="Image 2" class="img-fluid">
                </div>
            </div>
        <div class="text-center">
            <h1>Mobile Number: {{ $mobileNumber }}</h1>
            <!--<h2>Expiry Date: {{ $coupons->first()->end_date }}</h2>-->
        </div>
        <div class="container">
            <div class="row ">
                @foreach ($coupons as $coupon)
                @php
                $backgroundColor = $coupon->used ? 'used' : '';
                @endphp
                <div id="coupon-{{ $coupon->id }}" class="col-md-6 mx-auto center-box {{ $backgroundColor }}">
                    
                <div class="coupon_num">
                        {{ $coupon->voucher }}
                </div>
                    
                    <img src="{{ asset("images/athi{$coupon->coupon}.png") }}" alt="Coupon {{ $coupon->voucher }}" onclick="confirmMarkAsUsed({{ $coupon->id }}, {{ $coupon->used }})">
                    <!-- Display Voucher Number below each coupon -->
                </div>
                @if ($coupon->used)
                <div class="used-mobile-number">
                    Kupong gydig til: {{ $coupon->updated_at->format('d.m.y') }}
                    <br>
                    End Date: {{ \Carbon\Carbon::parse($coupon->end_date)->format('d.m.y') }}

                </div>
                @endif
                @endforeach
            </div>
        </div>

<div class="container">
            <div class="offer-text">
            <!-- Text: Offer for Customers -->
            <h4>Dette er kun et tilbud for kunder av Fargerike Majorstuen og Fargerike Alnabru.</h4>
            </div>

            <!-- Two Boxes with Store Information -->
        <div class="mb-4 row"> <!-- Ensure 'row' is added to create a flex container -->
            <!-- Fargerik Majorstuen -->
            <div class="col-md-6 mb-4 store-info-box"> <!-- Specify width for medium-sized screens -->
                <div class="border p-3">
                    <h5>Fargerik Majorstuen</h5>
                    <p><a href="google_maps_link_majorstuen" target="_blank">Sørkedalsveien 10<br>Colosseum Senter</a></p>
                    <p>Email: <a href="mailto:majorstuen@fargerike.no">majorstuen@fargerike.no</a></p>
                    <p>Åpningstider:<br>Man-Fre 06:30 - 18:00<br>Lør 08-15:30</p>
                </div>
            </div>
            
            <!-- Fargerike Alnabru -->
            <div class="col-md-6 mb-4 store-info-box"> <!-- Specify width for medium-sized screens -->
                <div class="border p-3">
                    <h5>Fargerike Alnabru</h5>
                    <p><a href="google_maps_link_alnabru" target="_blank">Verkseier Furulundsvei 4<br>Ovenfor Alnabru Senteret</a></p>
                    <p>Email: <a href="mailto:alnabru@fargerike.no">alnabru@fargerike.no</a></p>
                    <p>Åpningstider:<br>Man-Fre 06:30 - 19:00<br>Lør 08-16:00</p>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Copyright Information -->
            <p class="text-center copyright-text">(Copyright) Digifront.biz - {{ date('Y') }}</p>
        </div>
</div>        
            <!-- Popup Message Element -->
            <div class="modal" id="popup-message" tabindex="-1" role="dialog" style=" margin-top: 200px; ">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <p id="popup-text"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="popup-yes" onclick="confirmYes()">Yes</button>
                            <button type="button" class="btn btn-secondary" id="popup-no" data-dismiss="modal">No</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        var currentCouponId;
        var popupTimer;

        function confirmMarkAsUsed(couponId, used) {
            currentCouponId = couponId;

            if (used == 1) {
                showPopup("Coupon is already used!");
                popupTimer = setTimeout(() => {
                    closePopup();
                    removeCouponUsedMessage();
                }, 5000);
            } else {
                showPopup("Do you want to use this coupon?", true);
            }
        }

        function showPopup(message, showButtons) {
            document.getElementById('popup-text').innerText = message;

            if (showButtons) {
                document.getElementById('popup-yes').style.display = 'block';
                document.getElementById('popup-no').style.display = 'block';
            } else {
                document.getElementById('popup-yes').style.display = 'none';
                document.getElementById('popup-no').style.display = 'none';
            }

            $('#popup-message').modal('show');

            popupTimer = setTimeout(closePopup, 20000);
        }

        function confirmYes() {
            fetch('/mark-as-used', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ coupon_id: currentCouponId })
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.message, false);
                document.getElementById('coupon-' + currentCouponId).style.backgroundColor = 'lightblue';
                showUsedMobileNumberBox(currentCouponId);
                closePopup();
                getCouponsAction();
            })
            .catch(error => console.error('Error:', error));

            setTimeout(() => {
                location.reload();
            }, 3000);
        }

        function closePopup() {
            $('#popup-message').modal('hide');
            clearTimeout(popupTimer);
        }

        function showUsedMobileNumberBox(couponId) {
            document.querySelector('#coupon-' + couponId + ' .used-mobile-number').style.display = 'block';
        }

        function removeCouponUsedMessage() {
            $('#popup-message').modal('hide');
        }

        function getCouponsAction() {
            fetch('/get-coupons')
                .then(response => response.json())
                .then(data => {
                    console.log('Coupons refreshed:', data);
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
