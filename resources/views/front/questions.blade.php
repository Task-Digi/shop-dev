@extends('layouts.app')
@section('content')

    <div class="container">
        <center>
{{--            {{dd($access)}}--}}
            <div class="col-md-6 mt-5 mb-5">
                <div class="card ques" style="border: 1px solid rgb(0 0 0);">
                    <div style="background: #002a71;" class="card-header">
                        <h4 class="text-light">@if(isset($access)){{strtoupper(config('settings.customersName')[$access->Access_Customer_ID])}}@endif</h4>
                    </div>
                    <div class="card-body pb-0">
                        <div class="card-req">
                            @if(!empty($questions))
                                @foreach($questions as $qId => $question)
                                    {{--                                    {{dd(config('settings.select_question_group')[$qId], array_search($qId, config('settings.select_question_group')))}}--}}
                                    <div class="card mb-4" data-parent="qu{{$qId}}">
                                        <div class="card-body">
                                            <h5 class="card-title">{{$question}}</h5>
                                            <p class="card-text"></p>
                                            <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu{{$qId}}ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                            <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu{{$qId}}ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                            <button class="btn btn-warning ButtonClickedyellow" onclick="questionwarning(this)" data-child="qu{{$qId}}ans3" data-value="2"><i class="fa fa-question-circle fa-3x"></i></button>
                                            <input type="hidden" id="inputqu{{$qId}}">
                                        </div>
                                    </div>
                                    <small class="errorDetails float-left d-none"></small>
                                @endforeach
                            @endif

                            @if(!empty($questions_basic))
                                @foreach($questions_basic as $qId => $question)
                                    <div class="card mb-4" data-parent="qu{{$qId}}">
                                        <div class="card-body">
                                            <h5 class="card-title">{{$question}}</h5>
                                            <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu{{$qId}}ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                            <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu{{$qId}}ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                            <input type="hidden" id="inputqu{{$qId}}">
                                        </div>
                                    </div>
                                    <small class="errorDetails float-left d-none"></small>
                                @endforeach
                            @endif

                            @if(!empty($questions_css))
                                @foreach($questions_css as $qId => $question)
                                    <div class="card mb-4" data-parent="qu{{$qId}}">
                                        <div class="card-body">
                                            {{--@if(\Illuminate\Support\Facades\Session::has('access') && \Illuminate\Support\Facades\Session::get('access')->Access_Standard_ID !== 1005)
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
                                            @else--}}
                                            <h5 class="card-title">{{$question}}</h5>
                                            <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu{{$qId}}ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                            <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu{{$qId}}ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                            <input type="hidden" id="inputqu{{$qId}}">
                                            {{--                                    @endif--}}
                                        </div>
                                    </div>
                                    <small class="errorDetails float-left d-none"></small>
                                @endforeach
                            @endif
                        </div>

                        @if(!empty($questions_extra))
                            <div class="section-divider"></div>
                            <div class="mb-4">
                                <h4 class="mb-0">Et ekstra spørsmål vi har!</h4>
                                <small>(helt frivillig)</small>
                            </div>
                            @foreach($questions_extra as $qId => $question)
                                <div class="card mb-4" data-parent="qu{{$qId}}">
                                    <div class="card-body">
                                        <h5 class="card-title">{{$question}}</h5>
                                        @if(!Session::has('location'))
                                        <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu{{$qId}}ans1" data-value="1"><i class="fa fa-smile-o fa-3x"></i></button>
                                        <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu{{$qId}}ans2" data-value="0"><i class="fa fa-frown-o fa-3x"></i></button>
                                        <button class="btn btn-warning ButtonClickedyellow" onclick="questionwarning(this)" data-child="qu{{$qId}}ans3" data-value="2"><i class="fa fa-question-circle fa-3x" style="color: white;"></i></button>
                                        @else
                                            <button class="btn btn-success ButtonClickedgreen" onclick="questionsucess(this)" data-child="qu{{$qId}}ans1" data-value="1"><i class="fa fa-check fa-3x"></i></button>
                                            <button class="btn btn-danger ButtonClickedred" onclick="questiondanger(this)" data-child="qu{{$qId}}ans2" data-value="0"><i class="fa fa-times fa-3x"></i></button>
                                        @endif
                                        <input type="hidden" id="inputqu{{$qId}}">
                                    </div>
                                </div>
                                <small class="errorDetails float-left d-none"></small>
                            @endforeach
                        @endif

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
        data['_token'] = "{{ csrf_token() }}";
        $(document).ready(function () {
            $('#firstSub').attr('disabled', true);
        });

        $('#firstSub').on('click', function () {
            console.log("{{route('front.question.page.one')}}", '{{route('front.question.page.two')}}');
            saveQuestions("{{route('front.question.page.one')}}", '{{route('front.question.page.two')}}');
        });
    </script>
@endpush
@push('styles')
    <style>

    </style>
@endpush

