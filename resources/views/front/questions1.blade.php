@extends('layouts.app')
@section('content')
    <div class="container">
        <center>
            <div class="col-md-6 mt-5 mb-5">
                <div class="card ques" style="border: 1px solid rgb(0 0 0);">
                    <div style="background: #002a71;" class="card-header">
                        <h4 class="text-light">FARGERIKE ALNABRU</h4>
                    </div>
                    <div class="card-body pb-0">
                        <div class="card-req">
                            <div class="card mb-4" data-parent="qu1000001">
                                <div class="card-body">
                                    <h5 class="card-title">Oppfattet du stedet som innbydende sett utenfra?</h5>
                                    <p class="card-text"></p>
                                    <button class="btn btn-success " onclick="questionsucess(this)" data-child="qu1000001ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu1000001ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                    <button class="btn btn-warning" onclick="questionwarning(this)" data-child="qu1000001ans3" data-value="2"><i class="fa fa-question-circle fa-3x"
                                                                                                                                                 style="color: white;"></i></button>
                                    <input type="hidden" id="inputqu1000001">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu1000005">
                                <div class="card-body">
                                    <h5 class="card-title">Stilte de ansatte spørsmål rundt dine behov?</h5>
                                    <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu1000005ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu1000005ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                    <button class="btn btn-warning" onclick="questionwarning(this)" data-child="qu1000005ans3" data-value="2"><i class="fa fa-question-circle fa-3x"
                                                                                                                                                 style="color: white;"></i></button>
                                    <input type="hidden" id="inputqu1000005">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu1000009">
                                <div class="card-body">
                                    <h5 class="card-title">Var det lett å orientere seg i lokalet?</h5>
                                    <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu1000009ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu1000009ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                    <button class="btn btn-warning" onclick="questionwarning(this)" data-child="qu1000009ans3" data-value="2"><i class="fa fa-question-circle fa-3x"
                                                                                                                                                 style="color: white;"></i></button>
                                    <input type="hidden" id="inputqu1000009">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu1000013">
                                <div class="card-body">
                                    <h5 class="card-title">Var det kø i kasseområdet?</h5>
                                    <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu1000013ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu1000013ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                    <button class="btn btn-warning" onclick="questionwarning(this)" data-child="qu1000013ans3" data-value="2"><i class="fa fa-question-circle fa-3x"
                                                                                                                                                 style="color: white;"></i></button>
                                    <input type="hidden" id="inputqu1000013">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu1000017">
                                <div class="card-body">
                                    <h5 class="card-title">Var det tilfeldig at du besøkte oss i dag?</h5>
                                    <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu1000017ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu1000017ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                    <button class="btn btn-warning" onclick="questionwarning(this)" data-child="qu1000017ans3" data-value="2"><i class="fa fa-question-circle fa-3x"
                                                                                                                                                 style="color: white;"></i></button>
                                    <input type="hidden" id="inputqu1000017">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu10001">
                                <div class="card-body">
                                    <h5 class="card-title">Besøker du oss som privatkunde? (eller proff)</h5>
                                    <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu10001ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu10001ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                    <input type="hidden" id="inputqu10001">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu10002">
                                <div class="card-body">
                                    <h5 class="card-title">Har du besøkt dette stedet før?</h5>
                                    <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu10002ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                    <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu10002ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                    <input type="hidden" id="inputqu10002">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>

                            <div class="card mb-4" data-parent="qu1001">
                                <div class="card-body">
                                    <h5 class="card-title">Hvor sannsynlig er det at du vil anbefale oss til venner?</h5>
                                    <input id="ex6"
                                           type="range"
                                           data-slider-id='ex1Slider'
                                           data-slider-min="1"
                                           data-slider-max="10"
                                           data-slider-step="1"
                                           data-slider-value="10"
                                           data-value="10"
                                           value="10"
                                           data-provide="slider"/>
                                    <div class="box-minmax">
                                        <span class="d-inline-block" style="/* margin-right: 10rem; */font-size: 12px;position: absolute;margin-left: -115px;text-align: left;">
                                            <span class="d-block">1</span>
                                            <span class="d-block">Veldig liten</span>
                                        </span>
                                        <span class="d-inline-block" style="/* margin-right: 10rem; */font-size: 12px;">
                                            <span class="d-block" style="margin-left: -70px; position: relative; top: -16px; ">5</span>
                                            <span class="d-block hidden"></span>
                                        </span>
                                        <span class="d-inline-block" style="position: relative;  font-size: 12px;  text-align: right;  margin-right: -110px;">
                                            <span class="d-block">10</span>
                                            <span class="d-block">Veldig Stor</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none"></small>
                        </div>

                        <div class="section-divider"></div>
                        <div class="mb-4">
                            <h4 class="mb-0">Et ekstra spørsmål vi har!</h4>
                            <small>(helt frivillig)</small>
                        </div>
                        <div class="card mb-4" data-parent="qu100001">
                            <div class="card-body">
                                <h5 class="card-title">Opplevde du at smitteverntiltak ble tatt på alvor?</h5>
                                <button class="btn btn-success" onclick="questionsucess(this)" data-child="qu100001ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                <button class="btn btn-danger" onclick="questiondanger(this)" data-child="qu100001ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                <button class="btn btn-warning" onclick="questionwarning(this)" data-child="qu100001ans3" data-value="2"><i class="fa fa-question-circle fa-3x"
                                                                                                                                       style="color: white;"></i></button>
                                <input type="hidden" id="inputqu100001">
                            </div>
                        </div>
                        <small class="errorDetails float-left d-none"></small>

                    </div>
                    <button class="btn btn-success mb-4  ml-auto mr-auto" id="firstSub">FERDIG</button>
                </div>

            </div>
        </center>
    </div>
@endsection
@push('scripts')
    <script>
        let data = {};
        $(document).ready(function () {
            $('#firstSub').attr('disabled', true);
        });
        $('#firstSub').on('click', function () {
            let count = requiredCheck();
            console.log(count);
            if (count === 0) {
                data['_token'] = "{{ csrf_token() }}";
                let last = $('#inputqu100001').val();
                if(last !== '' && last !== null && last !== undefined) data['qu100001'] = last;
                $.ajax({
                    type: 'POST',
                    url: "{{route('front.question.page.one')}}",
                    data,
                    success: function (response) {
                        console.log(response);
                        window.location.replace('{{route('front.question.page.two')}}');
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log(textStatus, errorThrown);
                        {{--window.location.replace('{{route('front.question.page.one')}}');--}}
                    }
                });
            } else {
                $(this).attr('disabled', true);
            }
        });
    </script>
@endpush
@push('styles')
    <style>

    </style>
@endpush

