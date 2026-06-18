<?php

namespace App\Http\Controllers\Front;

use App\Models\Access;
use App\Models\Analytic;
use App\Models\Code;
use App\Events\SendSurveyMail;
use App\Models\Respond;
use App\Models\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class FrontController extends Controller
{
    public function index(Request $request, $access_code = '')
    {
        $access_code = strtoupper($access_code);

        if ($access_code !== 'A8H7G') {
            $access = Code::join('access as a', 'a.Access_ID', '=', 'codes.access')
                ->where('a.Access_Active', 1)
                ->where('code', $access_code);
            $accessTemp = $access->first();

            if (isset($accessTemp) && isset($accessTemp->from_date)) {
                //                $betweenDate = Carbon::createFromDate('Y-m-d', $accessTemp->from_date)->subDay(1);
                $access = $access->where(function ($where) {
                    $where->whereNotNull('from_date');
                    $where->whereDate('from_date', '<=', Carbon::now()->format('Y-m-d'));
                    //                    $where->whereNotBetween('from_date', [$betweenDate, Carbon::now()->format('Y-m-d')]);
                });
            }
            if (isset($accessTemp->to_date)) {
                //                $betweenDate = Carbon::createFromDate('Y-m-d', $accessTemp->from_date)->subDay(1);
                $access = $access->where(function ($where) {
                    $where->whereNotNull('to_date');
                    $where->whereDate('to_date', '>=', Carbon::now()->format('Y-m-d'));
                });
            }
            $access = $access->first();

            $request->session()->forget('respond');
            $request->session()->forget('location');
            $request->session()->forget('qCount');
            if ($access) {
                $request->session()->put('access', $access);
                $request->session()->put('qCount', $access->display_all == 1 ? 'A' : 'R');
                return view('front.front', compact('access', 'access_code'));
            } else {
                $access = null;
                return view('front.front', compact('access', 'access_code'))->with('error', 'Not a Valid URL!');
            }
        } else {
            $access = null;
            return view('front.retail', compact('access_code'));
        }
    }

    public function checkCustomer(Request $request, $access_code)
    {
        $access_code = strtoupper($access_code);
        //        dd($request->input());
        if ($access_code === 'A8H7G') {
            $customer = strtoupper($request->customer);
            $location = ucfirst($request->location);

            $request->session()->forget('respond');
            $request->session()->forget('location');
            $access = Access::where('Access_Active', 1)
                ->where('Access_Code', $customer)
                ->first();
            //            dd($access_code, $customer, $location, $access);
            if ($access) {
                $request->session()->put('access', $access);
                $request->session()->put('location', $location);
                $request->session()->put('qCount', 'R');
                //                dd($request->latitude, $request->longitude, $request->input());
                if (isset($request->latitude))
                    $request->session()->put('latitude', $request->latitude);
                if (isset($request->longitude))
                    $request->session()->put('longitude', $request->longitude);
                //                dd($request->session()->get('access'), $request->session()->get('location'));
                return response()->json(['status' => true, 'redirectUrl' => route('front.question.page.one')]);
            } else {
                return response()->json(['status' => false, 'redirectUrl' => route('front.home')]);
            }
        } else {
            return response()->json(['status' => false, 'redirectUrl' => route('front.home')]);
        }
    }

    public function questions(Request $request)
    {
        if ($request->session()->has('access') && $request->session()->has('qCount')) {
            $request->session()->forget('respond');
            $access = $request->session()->get('access');
            $qCount = $request->session()->get('qCount');

            $select = config('settings.select')[$access->Access_Standard_ID];
            $pageLists = array_keys($select);
            //            dd($select, $pageLists);

            /* Get pages in an order */
            if (!Cache::has('prePage')) {
                $val = reset($pageLists);
                $key = array_search($val, $pageLists);
                Cache::put('prePage', $key, 60 * 60);
            } else {
                $key = Cache::get('prePage');
                $key += 1;
                if (!array_key_exists($key, $pageLists)) {
                    $val = reset($pageLists);
                    $key = array_search($val, $pageLists);
                }
                Cache::put('prePage', $key, 60 * 60);
            }

            //            $page = $pageLists[array_rand ($pageLists , 1)];
            $page = $pageLists[Cache::get('prePage')];
            $questionsList = [];
            if ($qCount === 'R') {
                $questionsList = $select[$page];
            } else {
                foreach ($select as $sel)
                    $questionsList = array_merge($questionsList, $sel);
            }
            $questions = [];
            foreach ($questionsList as $list)
                $questions[$list] = config('settings.questions')[$list];

            /* Basic Questions */
            $selectBasic = config('settings.select_basic_question')[$access->Access_Standard_ID];
            $basicQueList = $selectBasic[$page];;
            $questions_basic = [];
            foreach ($basicQueList as $list)
                $questions_basic[$list] = config('settings.basic_questions')[$list];

            /* CSS Questions */
            $selectCss = config('settings.select_css_question')[$access->Access_Standard_ID];
            $cssQueList = $selectCss[$page];;
            $questions_css = [];
            foreach ($cssQueList as $list)
                $questions_css[$list] = config('settings.css_questions')[$list];

            /* Extra Questions */
            $selectExtra = config('settings.select_extra_question')[$access->Access_Standard_ID];
            $extraQueList = $selectExtra[$page];
            $questions_extra = [];
            foreach ($extraQueList as $list)
                $questions_extra[$list] = config('settings.extra_questions')[$list];

            //            dd($questions_basic, $questions_css, $questions_extra);
            return view('front.questions', compact('access', 'page', 'questions', 'questions_css', 'questions_basic', 'questions_extra'));
        } else {
            return redirect()->route('front.home')->with('error', 'This Access Code is not Available!');
        }
    }

    public function otherQuestions(Request $request)
    {
        if ($request->session()->has('respond')) {
            $access = $request->session()->get('access');
            return view('front.otherquestions', compact('access'));
        } else return redirect()->back();
    }

    public function thanks(Request $request)
    {
        if ($request->session()->has('access')) {
            $access = $request->session()->get('access');
            return view('front.thanks', compact('access'));
        } else return redirect()->back();
    }

    /**
     * Save All the Mandatory Answers in Session and do next
     *
     * @param Request $request
     * @param string $access_code
     *
     * @return JsonResponse|object
     * @auther A
     */
    public function saveAnswerMandatory(Request $request)
    {
        if ($request->session()->has('access')) {
            $access = $request->session()->get('access');
            $inputs = $request->except('_token');

            $respondData = [
                'Respond_IP' => $request->ip(),
                'Respond_Customer_ID' => $access->Access_Customer_ID,
            ];
            $cordinates = [];
            if ($request->session()->has('location'))
                $respondData['Respond_Customer_Location'] = $request->session()->get('location');
            if ($request->session()->has('latitude') || $request->session()->has('longitude'))
                $respondData['Respond_Customer_Cordinates'] = json_encode([
                    'lat' => $request->session()->get('latitude'),
                    'lon' => $request->session()->get('longitude'),
                ]);
            $createdRespond = Respond::create($respondData);
            Analytic::create([
                'Customer_ID' => $access->Access_Customer_ID,
                'Access_Code' => $access->Access_Code,
                'Date' => Carbon::now()->format('Y-m-d H:i:s')
            ]);
            if ($createdRespond) {
                $request->session()->put('respond', $createdRespond);

                foreach ($inputs as $key => $value) {
                    $id = str_replace('qu', '', $key);

                    $input['Respons_Respond_ID'] = $createdRespond->Respond_ID;
                    $input['Respons_Access_ID'] = $access->Access_ID;
                    $input['Respons_Customer_ID'] = $access->Access_Customer_ID;
                    $input['Respons_Source_ID'] = $access->Access_Source_ID;
                    $input['Questions_Standard_ID'] = $access->Access_Standard_ID;
                    $input['Respons_Question_ID'] = $id;
                    $input['Respons_Question_Answer'] = $value;
                    $createdResponse = Response::create($input);
                }
                Code::where('code_id', $access->code_id)->update(['count' => $access->count + 1]);
                //                event(new SendSurveyMail($createdRespond->Respond_ID));
                return redirect()->route('front.question.page.two');
            } else {
                return response()->json(['status' => 403, 'data' => ['message' => 'Sorry Something went wrong']]);
            }
        } else {
            return response()->json(['status' => 404, 'data' => ['message' => 'There is no Access Code like that!']]);
        }
    }

    /**
     * Save All the Answers ans save in DB
     *
     * @param Request $request
     *
     * @auther A
     * @return JsonResponse|object
     */
    public function saveAllAnswer(Request $request)
    {
        if ($request->session()->has('access') && $request->session()->has('respond')) {
            $respond = $request->session()->get('respond');

            $respondAnsInput['Respond_Email'] = $request->Respond_Email;
            $respondAnsInput['Respond_OtherInfo'] = $request->Respond_OtherInfo;
            $respondAnsInput['Respond_Contact'] = $request->Respond_Contact;
            $respondAnsInput['Respond_Name'] = $request->Respond_Name;
            $respondAnsInput['Respond_Phone'] = $request->Respond_Phone;
            $respondAnsInput['Respond_Newsletter'] = $request->Respond_Newsletter;
            $updateRespond = Respond::where('Respond_ID', $respond->Respond_ID)->update($respondAnsInput);

            if ($updateRespond) {
                return response()->json(['status' => 200, 'message' => 'Survey saves successfully...']);
            } else {
                return response()->json(['status' => 403, 'data' => ['message' => 'Sorry Something went wrong']]);
            }
        } else {
            return response()->json(['status' => 403, 'data' => ['message' => 'Sorry Something went wrong']]);
        }
    }

    /**
     * View Survey Details
     *
     * @param integer $id
     * @param Request $request
     *
     * @auther A
     * @return object
     */
    public function surveyDetails(int $id, Request $request)
    {
        $data['respond'] = Respond::where('Respond_ID', $id)->first();
        if (isset($data['respond']) && $data['respond'] !== null) {
            $data['status'] = 200;
            $data['respons'] = Response::where('Respons_Respond_ID', $data['respond']->Respond_ID)->get()->groupBy('Respons_Question_ID')->toArray();
        } else {
            $data['status'] = 404;
        }
        return view('front.mail', compact('data'));
    }

    /**
     * View Retail Routine
     *
     * @auther A
     * @return object
     */
    public function retailRoutine()
    {
        return view('front.routine');
    }

    /**
     * View Retail Routine
     *
     * @auther A
     * @return object
     */
    public function retailInformation()
    {
        return view('front.information');
    }
}
