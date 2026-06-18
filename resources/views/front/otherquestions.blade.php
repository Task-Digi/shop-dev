@extends('layouts.app')
@section('content')
    @php
        $workEnvironmentCode = ['HY6W1','1OPD8','PTY85','082Z1','QR72A', 'HBM23', '67HUD', 'GUT4E']
    @endphp
{{--    {{dd($access)}}--}}
    <div class="container">
        <center>
            <div class="col-md-6 mt-5 mb-4">
                <div class="card ques page-three" style="border: 1px solid rgb(0 0 0);">
                    <div style="background: #002a71;" class="card-header">
                        <h4 class="text-light">@if(isset($access)){{strtoupper(config('settings.customersName')[$access->Access_Customer_ID])}}@endif</h4>
                    </div>
                    <div class="card-body">
                        <div class="card mb-4" data-parent="qu12">
                            <div class="card-body">
                                <h5 class="card-title">Noe annet du har lyst til å fortelle oss?</h5>
                                <textarea class="form-control" id="validationTextarea" id="inputqu12" name="Respond_OtherInfo" placeholder="Noe annet"></textarea>

                            </div>
                        </div>
                        <div class="card mb-4 isContact d-none" data-parent="qu13">
                            <div class="card-body">
                                <h5 class="card-title">Kan vi kontakte deg om svaret over?</h5>
                                <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu13ans1" data-value="1"><i class="fa fa-check fa-3x" data-value="1"></i>
                                </button>
                                <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu13ans2" data-value="0"><i class="fa fa-times fa-3x" data-value="0"></i>
                                </button>
                                <input type="hidden" id="inputqu13" name="Respond_Contact">
                            </div>
                        </div>
                        <div class="card mb-4" data-parent="qu14">
                            <div class="card-body">
                                <h5 class="card-title">Trenger navn for å kunne kontakte deg.</h5>
                                <p class="card-text"><input type="text" class="form-control" placeholder="Navn" id="inputqu14" name="Respond_Name"></p>
                            </div>
                        </div>

                        <div class="card mb-4" data-parent="qu15">
                            <div class="card-body">
                                <h5 class="card-title">Mobil</h5>
                                <input type="text" class="form-control" placeholder="Mobil" id="inputqu15" name="Respond_Phone">
                                <small class="errorDetails float-left d-none"></small>
                            </div>
                        </div>

                        @if(!in_array($access->Access_Code, $workEnvironmentCode))
                        <div class="card mb-4" data-parent="qu17">
                            <div class="card-body">
                                <h5 class="card-title">Vil du være med på konkurransen?</h5>
                                <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu17ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu17ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                <input type="hidden" id="inputqu17">
                            </div>
                        </div>
                        <small class="errorDetails float-left d-none" style="margin-top: -22px;"></small>
                        @endif

                        <div class="card mb-4" data-parent="qu11">
                            <div class="card-body">
                                <h5 class="card-title">Legg inn din e-post adresse her! </h5>
                                <input type="email" id="inputqu11" name="Respond_Email" class="form-control" placeholder="E-post">
                                <small class="errorDetails float-left d-none"></small>
                            </div>
                        </div>

                        @if(!Session::has('location') && (!in_array($access->Access_Code, $workEnvironmentCode)))
                            <div class="card mb-4" data-parent="qu16">
                                <div class="card-body">
                                    <h5 class="card-title">Ønsker du å motta informasjon fra oss?</h5>
                                    <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu16ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                    <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu16ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                    <input type="hidden" id="inputqu16" name="Respond_Newsletter">
                                </div>
                            </div>
                            <small class="errorDetails float-left d-none" style="margin-top: -22px;"></small>
                        @endif
                    </div>
                    <i class="fa fa-smile-o fa-5x" style="color: green;"></i>
                    <button class="btn btn-success mb-4 ml-auto mr-auto" id="savePageTwo">SEND INN</button>
                </div>
            </div>
        </center>
    </div>
@endsection
@push('scripts')
    <script>
        let data = {};
        let arrLenth = 0;
        let error = false;

        $(document).ready(function () {
            $('[data-parent=qu14]').hide();
            $('[data-parent=qu15]').hide();
            $('[data-parent=qu11]').hide();
        });

        $('[data-parent=qu13] button').on('click', function () {
            let value = $(this).data().value;
            if (value === 1) {
                $('[data-parent=qu14]').show();
                $('[data-parent=qu15]').show();
            } else {
                $('[data-parent=qu14]').hide();
                $('[data-parent=qu15]').hide();
            }
        });

        $('[data-parent=qu17] button').on('click', function () {
            let value = $(this).data().value;
            let error = emailRequiredCheck();
            if (error) {
                // errorMessage('[data-parent=qu17]', true, message = 'Du må først fylle i e-post adressen!');
                // errorMessage('[name=Respond_Email]', true, message = 'Du må først fylle i e-post adressen!');
                // $('#savePageTwo').attr('disabled', true);

                if(value == 1) {
                    errorMessage('[data-parent=qu17]', true, message = 'Du må først fylle i e-post adressen!');
                    errorMessage('[name=Respond_Email]', true, message = 'Du må først fylle i e-post adressen!');
                    $('#savePageTwo').attr('disabled', true);
                }
                else {
                    errorMessage('[data-parent=qu17]', false, '');
                    errorMessage('[name=Respond_Email]', false, '');
                    $('#savePageTwo').removeAttr('disabled');
                }
            }
            else {
                // errorMessage('[data-parent=qu17]', false, '');
                // errorMessage('[name=Respond_Email]', false, '');
                // $('#savePageTwo').removeAttr('disabled');

                // let rerEmail = $('[data-parent=qu17] input').val();
                let mailValidation = isEmail($('[name=Respond_Email]').val());
                if(value == 1 && mailValidation === false) {
                    errorMessage('[data-parent=qu11]', true, message = 'Du må først fylle i e-post adressen!');
                    $('#savePageTwo').attr('disabled', true);
                }
                else {
                    errorMessage('[data-parent=qu11]', false, '');
                    errorMessage('[data-parent=qu17]', false, '');
                    $('#savePageTwo').removeAttr('disabled');
                }
            }
        });

        $('[name=Respond_Email]').on('change keyup', function () {
            let email = $(this).val();
            if (email !== '' && email !== null && email !== undefined) {
                error = true;
                let mailValidation = isEmail($(this).val());
                if (mailValidation === false) {
                    $('#savePageTwo').attr('disabled', true);
                    errorMessage('[name=Respond_Email]', true, message = 'Please put a valid e-mail id');
                } else {
                    $('#savePageTwo').removeAttr('disabled');
                    errorMessage('[name=Respond_Email]', false, '');
                    let check = emailRequiredCheck();
                    console.log(check, 'check');
                    if (check) {
                        errorMessage('[data-parent=qu16]', true, message = 'Du må først fylle i e-post adressen!');
                        errorMessage('[name=Respond_Email]', true, message = 'Du må først fylle i e-post adressen!');
                        $('#savePageTwo').attr('disabled', true);
                    } else {
                        errorMessage('[data-parent=qu16]', false, '');
                        errorMessage('[data-parent=qu17]', false, '');
                        errorMessage('[name=Respond_Email]', false, '');
                        $('#savePageTwo').removeAttr('disabled');
                    }
                }
                console.log(mailValidation);
            } else {
                let sub = $('[data-parent=qu16] input').val();
                let eReq = $('[data-parent=qu17] input').val();
                if(sub == 1 || eReq == 1) {
                    errorMessage('[name=Respond_Email]', true, message = 'Du må først fylle i e-post adressen!');
                    $('#savePageTwo').attr('disabled', true);
                }
                else {
                    $('#savePageTwo').removeAttr('disabled');
                    errorMessage('[name=Respond_Email]', false, '');
                }
            }
        });

        $('[name=Respond_Phone]').on('change keyup', function () {
            error = true;
            let numValidation = isNumber($(this).val());
            if (numValidation === false) {
                $('#savePageTwo').attr('disabled', true);
                errorMessage('[name=Respond_Phone]', true, message = 'Please put a valid Phone Number');
            } else {
                $('#savePageTwo').removeAttr('disabled');
                errorMessage('[name=Respond_Phone]', false, '');
            }
            console.log(numValidation);
        });

        $('[name=Respond_OtherInfo]').on('change keyup', function () {
            let value = $(this).val();
            console.log(value);
            if (value !== '' && value !== null && value !== undefined) {
                $('.isContact').removeClass('d-none');
            } else {
                $('.isContact').addClass('d-none');
            }
        });

        $('[data-parent=qu16] button').on('click', function () {
            let check = emailRequiredCheck();
            let value = $('[data-parent=qu16] input').val();
            console.log(check, value, 'check');
            console.log(check, value, 'qu16');
            if (check) {
                if(value == 1) {
                    errorMessage('[data-parent=qu16]', true, message = 'Du må først fylle i e-post adressen!');
                    errorMessage('[name=Respond_Email]', true, message = 'Du må først fylle i e-post adressen!');
                    $('#savePageTwo').attr('disabled', true);
                }
                else {
                    errorMessage('[data-parent=qu16]', false, '');
                    errorMessage('[name=Respond_Email]', false, '');
                    $('#savePageTwo').removeAttr('disabled');
                }
            }
            else {
                let rerEmail = $('[data-parent=qu17] input').val();
                let mailValidation = isEmail($('[name=Respond_Email]').val());
                if(value == 1 && mailValidation === false) {
                    errorMessage('[data-parent=qu11]', true, message = 'Du må først fylle i e-post adressen!');
                    $('#savePageTwo').attr('disabled', true);
                }
                else {
                    errorMessage('[data-parent=qu11]', false, '');
                    errorMessage('[data-parent=qu16]', false, '');
                    $('#savePageTwo').removeAttr('disabled');
                }
            }
        });

        function emailRequiredCheck() {
            let subscribe = $('[data-parent=qu16] input').val();
            let rerEmail = $('[data-parent=qu17] input').val();
            let error = false;
            // console.log(subscribe, subscribe == 1, isEmail($('[name=Respond_Email]').val()) === false, 'subscribe');
            // console.log(rerEmail, rerEmail == 1, isEmail($('[name=Respond_Email]').val()) === false, 'rerEmail');
            if(subscribe == 1 || rerEmail == 1) {
                $('[data-parent=qu11]').show();
            }
            else {
                $('[data-parent=qu11]').hide();
            }
            if (subscribe == 1 && ($('[name=Respond_Email]').val() === '' || $('[name=Respond_Email]').val() === null || $('[name=Respond_Email]').val() === undefined) && isEmail($('[name=Respond_Email]').val()) === false) {
                let mailValidation = isEmail($('[name=Respond_Email]').val());
                if (mailValidation === false) {
                    error = true;
                }
                else {
                    error = false;
                }
                // if(rerEmail == 1 && ($('[name=Respond_Email]').val() === '' || $('[name=Respond_Email]').val() === null || $('[name=Respond_Email]').val() === undefined) && isEmail($('[name=Respond_Email]').val()) === false)
                //     error = true;
                // else error = true;
                console.log(error);
            } else {
                if(rerEmail == 1 && ($('[name=Respond_Email]').val() === '' || $('[name=Respond_Email]').val() === null || $('[name=Respond_Email]').val() === undefined) && isEmail($('[name=Respond_Email]').val()) === false) {
                    let mailValidation = isEmail($('[name=Respond_Email]').val());
                    if (mailValidation === false) {
                        error = true;
                    }
                    else {
                        error = false;
                    }
                }
                else error = false;
                // console.log(error);
            }
            return error;
        }

        /*function emailRequiredCheck() {
            let subscribe = $('[data-parent=qu16] input').val();
            let rerEmail = $('[data-parent=qu17] input').val();
            let error = false;
            // console.log(subscribe, subscribe == 1, isEmail($('[name=Respond_Email]').val()) === false, 'subscribe');
            // console.log(rerEmail, rerEmail == 1, isEmail($('[name=Respond_Email]').val()) === false, 'rerEmail');
            if(subscribe == 1 || rerEmail == 1) {
                $('[data-parent=qu11]').show();
                if (subscribe == 1 && ($('[name=Respond_Email]').val() === '' || $('[name=Respond_Email]').val() === null || $('[name=Respond_Email]').val() === undefined) && isEmail($('[name=Respond_Email]').val()) === false) {
                    let mailValidation = isEmail($('[name=Respond_Email]').val());
                    if (mailValidation === false) {
                        error = true;
                    }
                    else {
                        error = false;
                    }
                    // if(rerEmail == 1 && ($('[name=Respond_Email]').val() === '' || $('[name=Respond_Email]').val() === null || $('[name=Respond_Email]').val() === undefined) && isEmail($('[name=Respond_Email]').val()) === false)
                    //     error = true;
                    // else error = true;
                    console.log(error);
                }
                if(rerEmail == 1){
                    if(rerEmail == 1 && ($('[name=Respond_Email]').val() === '' || $('[name=Respond_Email]').val() === null || $('[name=Respond_Email]').val() === undefined) && isEmail($('[name=Respond_Email]').val()) === false) {
                        let mailValidation = isEmail($('[name=Respond_Email]').val());
                        if (mailValidation === false) {
                            error = true;
                        }
                        else {
                            error = false;
                        }
                    }
                    else error = false;
                    // console.log(error);
                }
            }
            else {
                $('[data-parent=qu11]').hide();
            }
            return error;
        }*/

        $('#savePageTwo').on('click', function () {
            let subscribe = $('[data-parent=qu16] input').val();
            let rerEmail = $('[data-parent=qu17] input').val();

            data['Respond_OtherInfo'] = $('[name=Respond_OtherInfo]').val();
            data['Respond_Contact'] = $('[name=Respond_Contact]').val();
            data['Respond_Name'] = $('[name=Respond_Name]').val();
            data['Respond_Phone'] = $('[name=Respond_Phone]').val();
            data['Respond_Newsletter'] = $('[name=Respond_Newsletter]').val();
            if(subscribe == 1 || rerEmail == 1)
                data['Respond_Email'] = $('[name=Respond_Email]').val();

            let tmpMailValidation = isEmail(data['Respond_Email']);
            if (tmpMailValidation === false && (subscribe == 1 || rerEmail == 1)) {
                error = true;
                $('#savePageTwo').attr('disabled', true);
                errorMessage('[name=Respond_Email]', true, message = 'Please put a valid e-mail id');
            } else {
                error = false;
                $('#savePageTwo').removeAttr('disabled');
                errorMessage('[name=Respond_Email]', false, '');
            }
            console.log(tmpMailValidation, error, data['Respond_Contact']);

            if (error === false) {
                $('#savePageTwo').removeAttr('disabled');
                console.log(data, 'data');
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    type: 'POST',
                    url: "{{route('front.question.page.two')}}",
                    data,
                    success: function (response) {
                        if(response.status === 200)  {
                            console.log(response, 'response');
                            window.location.replace('{{route('front.question.page.three')}}');
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log(textStatus, errorThrown);
                    }
                });
            } else {
                console.log('error');
                $('#savePageTwo').attr('disabled', true);
            }
        });
    </script>
@endpush
