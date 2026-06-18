@extends('layouts.app')
@section('content')
    <div class="container" id="landing-page">
        <center>
            <div class="col col-sm-12 col-md-10 col-lg-8 mt-3 mb-3">
                @if(isset($access) && $access->Access_Code === '1ZX2A')
                    <div style="border: 1px solid #000; border-radius: 4px; padding: 4px; font-weight: bolder; color: #002a71;">DEMO SURVEY</div>
                @endif
                <div class="card" style="border: 1px solid rgb(0 0 0);">
                    <div style="background: #002a71;" class="card-header">
                        <h4 class="text-light">@if(isset($access)){{strtoupper(config('settings.customersName')[$access->Access_Customer_ID])}}@endif</h4>
                    </div>
                    <div class="card-body">
                        @include('front.validationMessages')
                        <h4 class="mb-5">@if(isset($access) && array_key_exists($access->Access_Standard_ID, config('settings.standards'))) {{strtoupper(config('settings.standards')[$access->Access_Standard_ID])}} @endif</h4>
                        <img class="img-fluid" src="@if(isset($access)){{ asset('images/'.config('settings.customersLogo')[$access->Access_Customer_ID]) }}@else{{ asset('images/fargerike.png') }}@endif"
                             alt="@if(isset($access)){{strtoupper(config('settings.customersName')[$access->Access_Customer_ID])}}@endif">
                        <h5 class="mb-5 mt-4">JA, JEG VIL VÆRE MED!</h5>

                        <a href="@if(isset($access)){{route('front.question.page.one')}}@else#@endif">
                            <button class="btn btn-success front-btn"><i class="fa fa-smile-o fa-5x"></i></button>
                        </a>
                        <h6 style="text-align: left;" class="mt-4"><i class="fa fa-star"></i> DISCLAIMER TEXT </h6>
                    </div>
                </div>
            </div>
        </center>
    </div>
@endsection

@push('scripts')
    <script>
        let data = {};
        data['_token'] = "{{ csrf_token() }}";
        $(document).ready(function () {
            $('#firstSub').attr('disabled', true);
        });
    </script>
@endpush
