@extends('layouts.app-dashboard')
@section('content')
    <div class="chart section-bottom">
        <div class="row">
            <div class="col col-sm-12">
                <div class="row">
                    <div class="col">
                        <div class="page-header">
                            <h5>Comments</h5>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        @if($data['responds']->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="white-space: nowrap">Date</th>
                                        <th>NPS Score</th>
                                        <th>Comments</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>IP</th>
                                        {{--                                        <th style="white-space: nowrap">Customer ID</th>--}}
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['responds'] as $sId => $respond)
                                        <tr>
                                            <td style="white-space: nowrap">{{\Carbon\Carbon::parse($respond->created_at)->format('d-m')}}</td>
                                            <td>{{$respond->Respons_Question_Answer}}</td>
                                            <td>{{$respond->Respond_OtherInfo}}</td>
                                            <td>{{$respond->Respond_Email}}</td>
                                            <td>{{$respond->Respond_Name}}</td>
                                            <td>{{$respond->Respond_Phone}}</td>
                                            <td>{{$respond->Respond_IP}}</td>
                                            {{--                                            <td>{{$respond->Respond_Customer_ID}}</td>--}}
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <div class="alert alert-danger"><strong>Beklager!!!</strong> Ingen data funnet.</div>
                        @endif
                        {{$data['responds']->links()}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

