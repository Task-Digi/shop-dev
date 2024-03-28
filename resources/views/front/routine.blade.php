@extends('layouts.app')
@section('content')
    <div class="container" id="routine-page">
        <center>
            <div class="col col-sm-12 col-md-10 col-lg-8 mt-3 mb-3">
                <div class="card card-retail" style="border: 1px solid rgb(0 0 0);">
                    <div style="background: #002a71;" class="card-header p-0">
                        @include('layouts.retailMenu', ['heading' => 'Rutiner'])
                    </div>
                    <div class="card-body" style="">
                        @include('front.validationMessages')

                        <p>
                            Svar på undersøkelsen umiddelbart etter at du er på stedet og ikke etter at du f.eks. kommer hjem.
                        </p>

                        <p>
                            Svar på de fem nøkkelspørsmålene som stilles og evt. tilleggsspørsmål.
                        </p>

                        <p>
                            Viktig at du prøver å svare på alle spørsmål, men hvis det ikke relevant eller du ikke vet svaret så velger du "?"
                        </p>

                        <p class="mb-0">
                            Ønsker du å delta i konkurransen må du legge igjen din e-post adresse. Den brukes til å identifisere deg og kvalitetssikre dine svar, men vil ikke bli benyttet til noe annet. Brukerdata vil ikke bli delt med stedet eller kjeden du besøker.
                        </p>
                    </div>
                </div>
            </div>
        </center>
    </div>
@endsection
