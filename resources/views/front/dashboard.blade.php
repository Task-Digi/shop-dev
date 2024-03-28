@extends('layouts.app-dashboard')
@section('content')
    <div class="noOfResponse section-bottom">
        <div class="row">
            <div class="col">
                <div class="page-header">
                    <h5>No of Responses</h5>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-sm-4">
                <div class="name text-center">Week</div>
                <div class="card">
                    <div class="card-body text-center">{{$data['noOfResponse']['week'] ?? 0}}</div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="name text-center">Month</div>
                <div class="card">
                    <div class="card-body text-center">{{$data['noOfResponse']['month'] ?? 0}}</div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="name text-center">Year</div>
                <div class="card">
                    <div class="card-body text-center">{{$data['noOfResponse']['year'] ?? 0}}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="chart section-bottom">
        <div class="row">
            <div class="col col-sm-6">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>No of Responses</h5>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="chart-container">
                            <canvas id="chart-no-of-response-count"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col col-sm-6">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>Source mix</h5>
                        </div>
                    </div>
                </div>
{{--                {{dd($data['responses']['count_labels'], $data['sourceCount'])}}--}}
                <div class="row">
                    <div class="col">
                        <div id="canvas-holder" style="margin-left: auto; margin-right: auto;">
                            @if(!empty($data['sourceCount']))
                            <canvas id="chart-area" class="mr-auto ml-auto d-block"></canvas>
                            <div id="chartjs-tooltip">
                                <table></table>
                            </div>
                            @else
                                <div class="alert alert-danger"><strong>Beklager!!!</strong> Ingen data funnet.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="npsScore section-bottom">
        <div class="row">
            <div class="col col-sm-12">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>CSS Score</h5>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="nps-out">
                            @if(array_key_exists('total', $data['cssCount']['ans']) && array_key_exists('total', $data['cssCount']['que']))
                                <div class="single">
                                    <div class="month">Total</div>
                                    <div class="avg">@if($data['cssCount']['que']['total']['total'] != 0){{round(($data['cssCount']['ans']['total']['total']/$data['cssCount']['que']['total']['total'])*10, 1)}}@else 0 @endif</div>
                                </div>
                                @php
                                    unset($data['cssCount']['que']['total']);
                                    unset($data['cssCount']['ans']['total']);
                                    $data['cssCount']['que'] = array_reverse($data['cssCount']['que']);
                                    $data['cssCount']['ans'] = array_reverse($data['cssCount']['ans']);
                                @endphp
                            @endif
                            @foreach($data['cssCount']['que'] as $month => $css)
                                <div class="single">
                                    <div class="month">{{$month}}</div>
                                    <div class="avg">@if($css['total'] != 0){{round(($data['cssCount']['ans'][$month]['total']/$css['total']) * 10, 1)}}@else 0 @endif</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

{{--    @if($basicQuesCountEmpty === false)--}}
    <div class="chart section-bottom">
        <div class="row">
            <div class="col col-sm-12">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>Basic Question Answer Score</h5>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="vertical-align:middle;">Questions</th>
                                        <th rowspan="2" style="vertical-align:middle; text-align: center;">Total ( % )</th>
                                        <th colspan="6" style="text-align:center;">Months ( % )</th>
                                    </tr>
                                    <tr>
                                        @foreach(array_keys($data['basicQuesCount']) as $month)
                                            <th style="text-align:center;">{{$month}}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                @if(array_key_exists($standard['id'], config('settings.standard_basic_ques')))
                                    @foreach(config('settings.basic_questions') as $qId => $que)
{{--                                        @if($qId == 10003)--}}
{{--                                            {{dd($qId, config('settings.standard_basic_ques')[$standard['id']][0], array_search(100030, config('settings.standard_basic_ques')[$standard['id']]))}}--}}
{{--                                        @endif--}}
                                        @if(array_search($qId, config('settings.standard_basic_ques')[$standard['id']], true) !== false)
                                        <tr>
                                            <td>{{$que}}</td>
                                            @foreach(config('settings.answers') as $key => $ans)
                                                @if($key == 1)
                                                    @if(!empty($data['basicQuesAnsTotalCount']['Total']))
                                                        @if(array_key_exists($qId, $data['basicQuesAnsTotalCount']['Total']))
{{--                                                            {{dd($data['basicQuesAnsTotalCount']['Total'], $data['basicQuesTotalCount']['Total'], $qId)}}--}}
                                                            <td style="text-align:center;">
                                                                @if(array_key_exists($key, $data['basicQuesAnsTotalCount']['Total'][$qId]))
                                                                    {{ round(($data['basicQuesAnsTotalCount']['Total'][$qId][$key][0]['Ans_Count']/$data['basicQuesTotalCount']['Total'][$qId][0]['Que_Count']) * 100) }}
                                                                @else 0 @endif
                                                            </td>
                                                        @else
                                                            <td style="text-align:center;"> 0 </td>
                                                        @endif
                                                    @else
                                                        <td style="text-align:center;"> 0 </td>
                                                    @endif
                                                @endif
                                            @endforeach
                                            @foreach(array_keys($data['basicQuesCount']) as $month)
                                                @foreach(config('settings.answers') as $aId => $ansName)
                                                    @if($aId == 1)
                                                        @if(array_key_exists($qId, $data['basicQuesCount'][$month]) && array_key_exists($qId, $data['basicQuesMonthTotalCount'][$month]))
                                                            @if(array_key_exists($aId, $data['basicQuesCount'][$month][$qId]))
                                                                <td style="text-align:center;">{{round(($data['basicQuesCount'][$month][$qId][$aId][0]['Ans_Count']/$data['basicQuesMonthTotalCount'][$month][$qId][0]['Que_Count']) * 100)}}</td>
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
                                        @endif
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{{--    @endif--}}

{{--    @if($extraQuesCountEmpty === false)--}}
        <div class="chart section-bottom">
            <div class="row">
                <div class="col col-sm-12">
                    <div class="row">
                        <div class="col">
                            <div class="page-header">
                                <h5>Extra Question Answer Score</h5>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th rowspan="2" style="vertical-align:middle;">Questions</th>
                                        <th rowspan="2" style="vertical-align:middle; text-align: center;">Total ( % )</th>
                                        <th colspan="6" style="text-align:center;">Months ( % )</th>
                                    </tr>
                                    <tr>
                                        @foreach(array_keys($data['basicQuesCount']) as $month)
                                            <th style="text-align:center;">{{$month}}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(array_key_exists($standard['id'], config('settings.standard_extra_ques')))
                                        @foreach(config('settings.extra_questions') as $qId => $que)
                                            @if(array_search($qId, config('settings.standard_extra_ques')[$standard['id']], true) !== false)
                                                <tr>
                                                    <td>{{$que}}</td>
                                                    @foreach(config('settings.answers') as $key => $ans)
                                                        @if($key == 1)
                                                            @if(!empty($data['extraQuesAnsTotalCount']['Total']))
                                                                @if(array_key_exists($qId, $data['extraQuesAnsTotalCount']['Total']))
                                                                    <td style="text-align:center;">
                                                                        @if(array_key_exists($key, $data['extraQuesAnsTotalCount']['Total'][$qId]))
                                                                            {{ round(($data['extraQuesAnsTotalCount']['Total'][$qId][$key][0]['Ans_Count']/$data['extraQuesTotalCount']['Total'][$qId][0]['Que_Count']) * 100) }}
                                                                        @else 0 @endif
                                                                    </td>
                                                                @else
                                                                    <td style="text-align:center;"> 0 </td>
                                                                @endif
                                                            @else
                                                                <td style="text-align:center;"> 0 </td>
                                                            @endif
                                                        @endif
                                                    @endforeach
                                                    @foreach(array_keys($data['extraQuesCount']) as $month)
                                                        @foreach(config('settings.answers') as $aId => $ansName)
                                                            @if($aId == 1)
                                                                @if(array_key_exists($qId, $data['extraQuesCount'][$month]) && array_key_exists($qId, $data['extraQuesMonthTotalCount'][$month]))
                                                                    @if(array_key_exists($aId, $data['extraQuesCount'][$month][$qId]))
                                                                        <td style="text-align:center;">{{round(($data['extraQuesCount'][$month][$qId][$aId][0]['Ans_Count']/$data['extraQuesMonthTotalCount'][$month][$qId][0]['Que_Count']) * 100)}}</td>
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
                                            @endif
                                        @endforeach
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
{{--    @endif--}}
@endsection

@push('scripts')
    <script>
        let color = Chart.helpers.color;

        /* For Pie Chart*/
        let pieData = [];
        let pieColour = [];
        let pieLabel = [];
        @foreach($data['sourceCount'] as $s_id => $source)
            pieData.push({{count($source)}});
            pieColour.push(getRandomColor());
            pieLabel.push('{{config('settings.source')[$s_id]}}');
        @endforeach
        console.log(pieData, pieLabel, pieColour);
        let configPie = {
            type: 'pie',
            data: {
                datasets: [{
                    data: pieData,
                    backgroundColor: pieColour,
                    label: 'Customer Mix'
                }],
                labels: pieLabel
            },
            options: {
                responsive: true,
                legend: {
                    display: true
                },
                tooltips: {
                    enabled: false,
                }
            }
        };

        window.onload = function() {
            let ctx = document.getElementById('chart-no-of-response-count').getContext('2d');
            let config = createConfig('top', 'red', {!! json_encode($data['responses']['months_labels']) !!}, {!! json_encode($data['responses']['responseCounts']) !!});
            let noOfRes = new Chart(ctx, config);

            /* Load Pie chart on load */
            Chart.defaults.global.tooltips.custom = function(tooltip) {
                // Tooltip Element
                let tooltipEl = document.getElementById('chartjs-tooltip');

                // Hide if no tooltip
                if (tooltip.opacity === 0) {
                    tooltipEl.style.opacity = 0;
                    return;
                }

                // Set caret Position
                tooltipEl.classList.remove('above', 'below', 'no-transform');
                if (tooltip.yAlign) {
                    tooltipEl.classList.add(tooltip.yAlign);
                } else {
                    tooltipEl.classList.add('no-transform');
                }

                function getBody(bodyItem) {
                    return bodyItem.lines;
                }

                // Set Text
                if (tooltip.body) {
                    let titleLines = tooltip.title || [];
                    let bodyLines = tooltip.body.map(getBody);

                    let innerHtml = '<thead>';

                    titleLines.forEach(function(title) {
                        innerHtml += '<tr><th>' + title + '</th></tr>';
                    });
                    innerHtml += '</thead><tbody>';

                    bodyLines.forEach(function(body, i) {
                        let colors = tooltip.labelColors[i];
                        let style = 'background:' + colors.backgroundColor;
                        style += '; border-color:' + colors.borderColor;
                        style += '; border-width: 2px';
                        let span = '<span class="chartjs-tooltip-key" style="' + style + '"></span>';
                        innerHtml += '<tr><td>' + span + body + '</td></tr>';
                    });
                    innerHtml += '</tbody>';

                    let tableRoot = tooltipEl.querySelector('table');
                    tableRoot.innerHTML = innerHtml;
                }

                let positionY = this._chart.canvas.offsetTop;
                let positionX = this._chart.canvas.offsetLeft;

                // Display, position, and set styles for font
                tooltipEl.style.opacity = 1;
                tooltipEl.style.left = positionX + tooltip.caretX + 'px';
                tooltipEl.style.top = positionY + tooltip.caretY + 'px';
                tooltipEl.style.fontFamily = tooltip._bodyFontFamily;
                tooltipEl.style.fontSize = tooltip.bodyFontSize;
                tooltipEl.style.fontStyle = tooltip._bodyFontStyle;
                tooltipEl.style.padding = tooltip.yPadding + 'px ' + tooltip.xPadding + 'px';
            };
            let ctxPie = document.getElementById('chart-area').getContext('2d');
            let customerMix = new Chart(ctxPie, configPie);
        };
    </script>
    <script>
        /*let labelX = [];
        let labelY = [];
        let ratingData = new Array();
        let presets = getRandomColor();
        let utils = Samples.utils;*/

        // labelX.push(0);
        {{--@for($i = 1; $i <= 10; $i++)
            labelX.push('{{$i}}');
            @if(array_key_exists($i, $data['ratingCount']))
                ratingData[{{$i-1}}] = {{$data['ratingCount'][$i][0]['Rating_Count']}}
            @else
                ratingData[{{$i-1}}] = 0;
            @endif
        @endfor--}}

        /*let maxLabel = Math.max(...ratingData);
        if(maxLabel % 5 === 0) maxLabel = (Math.floor(maxLabel/5))*5;
        else maxLabel = (Math.floor(maxLabel/5)+1)*5;

        let options = {
            maintainAspectRatio: false,
            spanGaps: false,
            elements: {
                line: {
                    tension: 0.000001
                }
            },
            plugins: {
                filler: {
                    propagate: false
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        autoSkip: false,
                        maxRotation: 0
                    }
                }]
            }
        };

        let npsChart = new Chart('chart-rating', {
            type: 'line',
            data: {
                labels: labelX,
                datasets: [{
                    backgroundColor: utils.transparentize(presets),
                    borderColor: presets,
                    data: ratingData,
                    label: 'Score',
                    fill: 'start'
                }]
            },
            options: Chart.helpers.merge(options, {
                title: {
                    text: 'fill: NPS Score',
                    display: true
                },
                elements: {
                    line: {
                        tension: 0.4
                    }
                }
            })
        });*/
    </script>
@endpush

@push('styles')
    <style>
        #canvas-holder {
            width: 100%;
            margin-top: 50px;
            text-align: center;
        }
        #chartjs-tooltip {
            opacity: 1;
            position: absolute;
            background: rgba(0, 0, 0, .7);
            color: white;
            border-radius: 3px;
            -webkit-transition: all .1s ease;
            transition: all .1s ease;
            pointer-events: none;
            -webkit-transform: translate(-50%, 0);
            transform: translate(-50%, 0);
        }

        .chartjs-tooltip-key {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-right: 10px;
        }
    </style>
@endpush

