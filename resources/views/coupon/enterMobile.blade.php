<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* You can keep any custom CSS here if needed. */
    </style>
</head>
<body>
<!-- Top Boxes with Images and Store Information -->
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="row">
                <!-- Image 1 -->
                <div class="col-md-6 text-center mb-4">
                    <img src="{{ asset('images/FABlogo.jpg') }}" alt="Image 1" class="img-fluid">
                </div>
                <!-- Image 2 -->
                <div class="col-md-6 text-center mb-4">
                    <img src="{{ asset('images/FMBlogo.png') }}" alt="Image 2" class="img-fluid">
                </div>
            </div>
            
            <!-- Mobile Number Input Box -->
            <form id="couponForm" action="/get-coupons" method="post" style="margin-bottom:20px">
                @csrf
                <div class="form-group">
                    <label for="mobile_nr">
                        <h4>Tast inn ditt mobil nummer så får du tilgang til dine kuponger. Er du ikke invitert eller registrert i forkant får du kun tilgang til et begrenset antall tilbud.
</h4>
                    </label>
                    <input type="text" placeholder="Mobilnummer" class="form-control" name="mobile_nr" id="mobile_nr" required>
                    <p id="errorMessage" class="text-danger" style="display: none;">Beklager men fant ikke ditt nummer i v책re systemer.</p>
                </div>
                @if(isset($message))
                    <div class="alert alert-danger">{{ $message }}</div>
                @endif
                <button type="submit" class="btn btn-primary" onclick="showMessage()">SEND</button>
            </form>
            
            <!-- Text: Offer for Customers -->
            <h4>Dette er kun et tilbud for kunder av Fargerike Majorstuen og Fargerike Alnabru.</h4>


            <!-- Two Boxes with Store Information -->
<div class="mb-4 row"> <!-- Ensure 'row' is added to create a flex container -->
    <!-- Fargerik Majorstuen -->
    <div class="col-md-6 mb-4"> <!-- Specify width for medium-sized screens -->
        <div class="border p-3">
            <h5>Fargerik Majorstuen</h5>
            <p><a href="google_maps_link_majorstuen" target="_blank">Sørkedalsveien 10<br>Colosseum Senter</a></p>
            <p>Email: <a href="mailto:majorstuen@fargerike.no">majorstuen@fargerike.no</a></p>
            <p>Åpningstider:<br>Man-Fre 06:30 - 18:00<br>Lør 08-15:30</p>
        </div>
    </div>
    
    <!-- Fargerike Alnabru -->
    <div class="col-md-6 mb-4"> <!-- Specify width for medium-sized screens -->
        <div class="border p-3">
            <h5>Fargerike Alnabru</h5>
            <p><a href="google_maps_link_alnabru" target="_blank">Verkseier Furulundsvei 4<br>Ovenfor Alnabru Senteret</a></p>
            <p>Email: <a href="mailto:alnabru@fargerike.no">alnabru@fargerike.no</a></p>
            <p>Åpningstider:<br>Man-Fre 06:30 - 19:00<br>Lør 08-16:00</p>
        </div>
    </div>
</div>


            <!-- Copyright Information -->
            <p class="text-center">(Copyright) Digifront.biz - {{ date('Y') }}</p>
        </div>
    </div>
</div>




<!-- Include Bootstrap JS and jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
   $(document).ready(function() {
        $('#couponForm').submit(function(e) {
            if ($('#message').text().trim() === '') {
                $('#errorMessage').hide();
            } else {
                $('#errorMessage').show();
            }
        });
    });
</script>

</body>
</html>
