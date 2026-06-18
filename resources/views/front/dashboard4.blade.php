@extends('layouts.app-dashboard')
@section('content')

    <div class="chart section-bottom">
        <div class="row">
            <div class="col col-sm-12">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>Question Answer Score</h5>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        @if(!empty($data['queAnsCount']))
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>Questions</th>
                                    @foreach(config('settings.answers') as $key => $ans)
                                        <th style="text-align:center;">{{$ans}}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['queAnsCount'] as $qId => $ans)
                                        @if(array_key_exists($qId, config('settings.questions')))
                                            <tr>
                                                <td>{{config('settings.questions')[$qId]}}</td>
                                                @foreach(config('settings.answers') as $aId => $ansName)
                                                    @if(array_key_exists($aId, $ans))
                                                        <td style="text-align:center;">{{$ans[$aId][0]['Ans_Count']}}</td>
                                                    @else
                                                        <td style="text-align:center;"> -</td>
                                                    @endif
                                                @endforeach
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <div class="alert alert-danger"><strong>Beklager!!!</strong> Ingen data funnet.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

