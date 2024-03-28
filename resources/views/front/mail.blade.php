<!doctype html>
<html>
<head>
    <title>Survey Result</title>
    <style type="text/css">
        body {
            background: #9B9B9B;
            padding-left: 5rem;
            padding-right: 5rem;
            padding-top: 3rem;
        }
        .singleBlock {
            background: #ededed;
            border: 2px solid #000;
            margin-bottom: 20px;
            display: list-item;
            list-style: none;
        }
        .singleBlock .questions {
            width: 80%;
            display: inline-block;
            font-size: 1rem;
            padding: 15px 0px 15px 15px;
        }
        .singleBlock .answers {
            width: 9%;
            display: inline;
            float: right;
            font-size: 1rem;
            background: #bcbcbc;
            text-align: center;
            padding: 15px;
        }
        .viewLink {
            margin-bottom: 3rem;
            margin-top: 2rem;
        }
        .viewLink a {
            background: #07167f;
            color: #fff;
            padding: 10px;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #07167f;
        }
        .viewLink a:hover {
            background: #fff;
            color: #07167f;
            border: 1px solid #07167f;
        }
        @media only screen and (max-device-width: 768px) {
            body {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                padding-top: 3rem !important;
            }
            .singleBlock {
                margin-bottom: 20px;
                padding: 15px 15px;
            }
            .singleBlock .questions {
                width: 80%;
                display: inline-block;
                font-size: 0.8rem;
                padding: 15px 0px 15px 0px;
            }
            .singleBlock .answers {
                width: 9%;
                display: inline;
                float: right;
                font-size: 0.8rem;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="header" style="background: #002a71; padding: 20px; color: #fff; font-family: Roboto; margin-bottom: 20px;">QUESTIFY Survey FARGERIKE</div>
        @if($data['status'] == 200)
            <div class="singleBlock" style="padding: 15px;">
                <div style="margin-bottom: 10px;">
                    <strong>IP : </strong> {{$data['respond']->Respond_IP ?? ' - '}}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Customer ID : </strong> {{$data['respond']->Respond_Customer_ID ?? ' - '}}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>EMail : </strong> {{$data['respond']->Respond_Email ?? ' - '}}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Name : </strong> {{$data['respond']->Respond_Name ?? ' - '}}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Phone No : </strong> {{$data['respond']->Respond_Phone ?? ' - '}}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Other Info : </strong> {{$data['respond']->Respond_OtherInfo ?? ' - '}}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Respond Contact : </strong> @if($data['respond']->Respond_Contact == 1) JA @else NEI @endif
                </div>
                <div>
                    <strong>Respond Newsletter : </strong> @if($data['respond']->Respond_Newsletter == 1) JA @else NEI @endif
                </div>
            </div>
            @foreach($data['respons'] as $qId => $ans)
                @if(array_key_exists($qId, config('settings.questions')))
                    <div class="singleBlock">
                        <div class="questions">{{config('settings.questions')[$qId]}}</div>
                        <div class="answers">{{config('settings.answers')[$ans[0]['Respons_Question_Answer']]}}</div>
                    </div>
                @elseif($qId == 10001)
                    <div class="singleBlock">
                        <div class="questions">Besøker du oss som privatkunde? (eller proff)</div>
                        <div class="answers">@if($ans[0]['Respons_Question_Answer'] == 1) JA @else NEI @endif</div>
                    </div>
                @elseif($qId == 10002)
                    <div class="singleBlock">
                        <div class="questions">Har du besøkt dette stedet før?</div>
                        <div class="answers">@if($ans[0]['Respons_Question_Answer'] == 1) JA @else NEI @endif</div>
                    </div>
                @elseif($qId == 1001)
                    <div class="singleBlock">
                        <div class="questions">Hvor sannsynlig er det at du vil anbefale oss til venner?</div>
                        <div class="answers">{{$ans[0]['Respons_Question_Answer']}}</div>
                    </div>
                @elseif($qId == 100001)
                    <div class="singleBlock">
                        <div class="questions">Opplevde du at smitteverntiltak ble tatt på alvor?</div>
                        <div class="answers">{{config('settings.answers')[$ans[0]['Respons_Question_Answer']]}}</div>
                    </div>
                @endif
            @endforeach
        @else
            <div style="background: #5F0101; color: #fff; padding: 15px; font-family: Roboto;">
                Survey Not Found
            </div>
        @endif
        <div class="viewLink">
            @if(isset($mail) && $mail == true)
            <a href="{{route('front.survey.details', $data['respond']->Respond_ID)}}" target="_blank">Click to View</a>
            @endif
        </div>
    </div>
</body>
</html>
