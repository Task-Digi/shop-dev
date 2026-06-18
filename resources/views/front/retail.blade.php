@extends('layouts.app')
@section('content')
    <div class="container" id="landing-page">
        <center>
            <div class="col col-sm-12 col-md-10 col-lg-8 mt-3 mb-3">
                <div class="card card-retail" style="border: 1px solid rgb(0 0 0);">
                        <div style="background: #002a71;" class="card-header p-0">
                            @include('layouts.retailMenu', ['heading' => 'HØYSKOLEN KRISTIANIA'])
                        </div>
                        <div class="card-body" style="padding-top: 0px;">
                            @include('front.validationMessages')
                            {{--                            <h4 class="">@if(array_key_exists('1012', config('settings.standards'))) {{strtoupper(config('settings.standards')['1012'])}} @endif</h4>--}}
                            <img class="img-fluid" src="{{ asset('images/hk.png') }}" alt="{{strtoupper('HØYSKOLEN KRISTIANIA')}}">
                            {{--                            <h5 class="mb-3" style="">JA, JEG VIL VÆRE MED!</h5>--}}

                            <p>Her legger du inn hvor du er og hvilken kjede du besøker.</p>

                            <div class="row">
                                <div class="offset-sm-4 col-sm-4 mb-3">
                                    <input placeholder="Gate eller plassering av butikk" class="form-control" name="front_streat_name" id="front_streat_name" required>
                                    <small class="errorDetails float-left d-none"></small>
                                </div>
                                <input type="hidden" class="form-control" name="lat" id="lat">
                                <input type="hidden" class="form-control" name="lng" id="lng">
                            </div>

                            <div class="row select-cus-box-row align-content-center">
                                <div class="col-10 col-sm-4 col-lg-4 mx-auto">
                                    <div class="click-box" data-customer = "FY76J">
                                        <img class="img-fluid" src="{{ asset('images/btn-bg2.png')}}" alt="{{strtoupper(config('settings.customersName')['500106'])}}">
                                        <p>{{config('settings.customersName')['500106']}}</p>
                                    </div>
                                </div>
                                <div class="col-10 col-sm-4 col-lg-4 mx-auto">
                                    <div class="click-box" data-customer = "HS8R6">
                                        <img class="img-fluid" src="{{ asset('images/btn-bg2.png')}}" alt="{{config('settings.customersName')['500107']}}">
                                        <p>{{config('settings.customersName')['500107']}}</p>
                                    </div>
                                </div>
                                <div class="col-10 col-sm-4 col-lg-4 mx-auto">
                                    <div class="click-box" data-customer = "L4B8D">
                                        <img class="img-fluid" src="{{ asset('images/btn-bg2.png')}}" alt="{{config('settings.customersName')['500108']}}">
                                        <p>{{config('settings.customersName')['500108']}}</p>
                                    </div>
                                </div>
                                <div class="col-10 col-sm-4 col-lg-4 mx-auto">
                                    <div class="click-box" data-customer = "X98OL">
                                        <img class="img-fluid" src="{{ asset('images/btn-bg2.png')}}" alt="{{config('settings.customersName')['500109']}}">
                                        <p>{{config('settings.customersName')['500109']}}</p>
                                    </div>
                                </div>
                                <div class="col-10 col-sm-4 col-lg-4 mx-auto">
                                    <div class="click-box" data-customer = "DUBLH">
                                        <img class="img-fluid" src="{{ asset('images/btn-bg2.png')}}" alt="{{config('settings.customersName')['500110']}}">
                                        <p>{{config('settings.customersName')['500110']}}</p>
                                    </div>
                                </div>
                            </div>
{{--                            @if(\Request::route()->getName() === 'front.retail.project1')--}}
{{--                                <div id="mapholder"></div>--}}
{{--                                <button class="btn btn-primary" onclick="getLocation();">Location</button>--}}
{{--                                <button onclick="getLocation()">Try It</button>--}}
{{--                            @endif--}}

                        </div>
                    </div>
            </div>
        </center>
    </div>
@endsection

@push('scripts')
    <script>
        "use strict";
        // $(document).ready(function () {
            var latitude;
            var longitude;
        // });
        $('#front_streat_name').on('keyup change', function () {
            let location = $(this).val();
            if(location !== '' && location !== undefined && location !== null) {
                getLocation();
                $('#front_streat_name').removeClass('error');
                $('#front_streat_name').next().filter('small.errorDetails').empty().addClass('d-none');
            }
            else
                errorMessage('#front_streat_name', true, 'Lokasjon er påkrevd');
        });

        $('.click-box').on('click', function () {
            let customer = $(this).data('customer');
            let location = $('#front_streat_name').val();
            if(location !== '' && location !== undefined && location !== null) {
                ajaxMethod(
                    "{{config('app.url')}}A8H7G",
                    {
                        _token : "{{ csrf_token() }}",
                        latitude,
                        longitude,
                        customer,
                        location
                    },
                    'POST',
                );
            }
            else errorMessage('#front_streat_name', true, 'Lokasjon er påkrevd');
        });

        function getLocation() {
            if (navigator.geolocation) {
                let x = navigator.geolocation.getCurrentPosition(showPosition);
            } else {
                x.innerHTML = "Geolocation is not supported by this browser.";
            }
        }

        function showPosition(position) {
            $('#lat').val(position.coords.latitude);
            $('#lng').val(position.coords.longitude);
            getPos();
        }

        function getPos() {
            latitude = $('#lat').attr('value');
            longitude = $('#lng').val();
        }
    </script>
@endpush
