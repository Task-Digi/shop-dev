@extends('layouts.app')
@section('content')
    <div class="container" id="information-page">
        <center>
            <div class="col col-sm-12 col-md-10 col-lg-8 mt-3 mb-3">
                <div class="card card-retail" style="border: 1px solid rgb(0 0 0);">
                    <div style="background: #002a71;" class="card-header p-0">
                        @include('layouts.retailMenu', ['heading' => 'Prosjekt'])
                    </div>
                    <div class="card-body" style="">
                        @include('front.validationMessages')

                        <p>
                            Prosjekt Mystery Shopper - Høsten 2021
                        </p>

                        <ul>
                            <li>
                                Oppsøk en av de nevnte kioskene under
                            </li>
                            <li>
                                Skriv hvor kiosken ligger (vei, gate, senter etc.) i fritekstområdet
                            </li>
                            <li>
                                Trykk på kiosken (i blått) når du er på stedet
                            </li>
                            <li>
                                Svar på fem spørsmål
                            </li>
                            <li>
                                Legg inn kontaktdetaljer om du ønsker å være med i konkurransen
                            </li>
                        </ul>

                        <p>
                            Lykke til!
                        </p>

                        <p class="mb-0">
                            Du kan evaluere kiosker over hele landet, men samme kiosk maks fem ganger.
                        </p>
                    </div>
                </div>
            </div>
        </center>
    </div>
@endsection
