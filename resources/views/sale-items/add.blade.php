@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
            integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous">
        </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script>
        <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
    </head>

    <div class="card">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <h4>Enter Product</h4>
                </div>
                <div class="col-md-6 text-md-right">
                    <a href="/Dashboard" class="btn btn-primary">DASHBOARD</a>
                </div>
            </div>

            <form method="POST" action="/home">
                @csrf

                <div class="sale-item-box border rounded p-3 mt-4 mb-5">


                    <div class="form-group row">
                        <label for="product_id" class="col-sm-2 col-form-label">Product ID:</label>
                        <div class="col-sm-4">
                            <input type="number" id="product_id" name="product_id" class="form-control" onblur="checkProductId()" required>
                        </div>
                        <div id="product_id-alert" class="col-sm-4">
                            <span class="text-danger"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="product_name" class="col-sm-2 col-form-label">Product Name:</label>
                        <div class="col-sm-4">
                            <input type="text" id="product_name" name="product_name" class="form-control" required>
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </div>

            </form>
        </div>
    </div>
    <script>
        function checkProductId() {
            var product_id = document.getElementById('product_id').value;
            var product_id_alert = document.getElementById('product_id-alert');
            $.ajax({
                url: '/validate-product-id',
                type: 'POST',
                data: {
                    productid: product_id,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                   console.log(response.success);
                }
            });
        }

    </script>



@endsection
