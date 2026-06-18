@extends('layouts.app-dashboard')
@section('content')

    <div class="chart section-bottom">
        <div class="row">
            <div class="col col-sm-12">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>Monthly Question Average Score</h5>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        @if(!empty($data['questionsList']))
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="vertical-align:middle;">Questions</th>
                                        <th rowspan="2" style="vertical-align:middle; text-align: center;">Total ( % )</th>
                                        <th colspan="6" style="text-align:center;">Months ( % )</th>
                                    </tr>
                                    <tr>
                                        @foreach(array_keys($data['queAnsAvg']) as $month)
                                            <th style="text-align:center;">{{$month}}</th>
                                        @endforeach
                                    </tr>
{{--                                    <tr>--}}
{{--                                        @foreach(config('settings.answers') as $key => $ans)--}}
{{--                                            @if($key == 1)--}}
{{--                                                <th style="text-align:center;">{{$ans}}</th>--}}
{{--                                            @endif--}}
{{--                                        @endforeach--}}
{{--                                        @foreach(array_keys($data['queAnsAvg']) as $month)--}}
{{--                                            @foreach(config('settings.answers') as $key => $ans)--}}
{{--                                                @if($key === 1)--}}
{{--                                                    <th style="text-align:center;">{{$ans}}</th>--}}
{{--                                                @endif--}}
{{--                                            @endforeach--}}
{{--                                        @endforeach--}}
{{--                                    </tr>--}}
                                </thead>
                                <tbody>
                                @foreach($data['questionsList'] as $qId => $que)
                                    <tr>
                                        <td>{{$que}}</td>
{{--                                        {{dd($data['standardQuesAnsTotalCount'], $data['standardQuesTotalCount'])}}--}}
                                        @foreach(config('settings.answers') as $key => $ans)
                                            @if($key === 1)
                                                @php
                                                    $up = $data['standardQuesAnsTotalCount']['Total'][$qId][$key][0]['Ans_Count'] ?? 0;
                                                    $down = $data['standardQuesTotalCount']['Total'][$qId][0]['Que_Count'] ?? 0;
                                                @endphp
{{--                                            {{dd($data['standardQuesAnsTotalCount']['Total'])}}--}}
                                                @if(array_key_exists($qId, $data['standardQuesAnsTotalCount']['Total']) && $down !== 0)
                                                    <td style="text-align:center;">{{ round(($up/$down) * 100) }}</td>
                                                @else
                                                    <td style="text-align:center;"> 0 </td>
                                                @endif
                                            @endif
                                        @endforeach
                                        @foreach(array_keys($data['queAnsAvg']) as $month)
                                            @foreach(config('settings.answers') as $aId => $ansName)
                                                @if($aId === 1)
                                                    @if(array_key_exists($qId, $data['queAnsAvg'][$month]) && array_key_exists($qId, $data['queCount'][$month]))
                                                        @if(array_key_exists($aId, $data['queAnsAvg'][$month][$qId]))
                                                            <td style="text-align:center;">{{round(($data['queAnsAvg'][$month][$qId][$aId][0]['Ans_Avg']/$data['queCount'][$month][$qId][0]['queCount']) * 100)}}</td>
                                                        @else
                                                            <td style="text-align:center;"> 0 </td>
                                                        @endif
                                                    @else
                                                        <td style="text-align:center;"> 0 </td>
                                                    @endif
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </tr>
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


    {{--    <div class="chart section-bottom">
            <div class="row">
                <div class="col col-sm-12">
                    <div class="row">
                        <div class="col">
                            <div class="page-header">
                                <h5>Standard Score</h5>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Standards</th>
                                            <th rowspan="2" class="text-center">Total</th>
                                            <th rowspan="2" class="text-center">Current Month</th>
                                            <th colspan="11" class="text-center">Months</th>
                                        </tr>
                                        <tr>
                                            @foreach($data['month_standards'] as $month => $arr)
                                                <th class="text-center">{{$month}}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $currentMonthTotal = 0;
                                            $monthTotal = [];
                                        @endphp
                                        @if(!empty($data['standards']))
                                            @foreach($data['standards'] as $sId => $standard)
                                                @php
                                                    $currentMonthTotal += count($data['current_month'][$sId]);
                                                @endphp
                                                <tr>
                                                    <td>@if(array_key_exists($sId, config('settings.standards'))){{config('settings.standards')[$sId]}}@else{{$sId}}@endif</td>
                                                    <td class="text-center">{{count($standard)}}</td>
                                                    <td class="text-center">{{count($data['current_month'][$sId])}}</td>
                                                    @foreach($data['month_standards'] as $month => $arr)
                                                        @if(array_key_exists($sId, $arr))
                                                            @php
                                                                if(array_key_exists($month, $monthTotal))
                                                                    $monthTotal[$month] += count($arr[$sId]);
                                                                else
                                                                    $monthTotal[$month] = count($arr[$sId]);
                                                            @endphp
                                                            <td class="text-center">{{count($arr[$sId])}}</td>
                                                        @else
                                                            @php
                                                                $monthTotal[$month] = 0;
                                                            @endphp
                                                            <td class="text-center"> - </td>
                                                        @endif
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td colspan="2" class="text-center">Respons Total</td>
                                                <td class="text-center">{{$currentMonthTotal}}</td>
                                                @foreach($data['month_standards'] as $month => $arr)
                                                    <td class="text-center">{{$monthTotal[$month]}}</td>
                                                @endforeach
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>--}}
@endsection

