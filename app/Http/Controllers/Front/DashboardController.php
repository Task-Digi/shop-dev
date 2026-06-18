<?php

namespace App\Http\Controllers\Front;

use App\Models\Access;
use App\Models\Respond;
use App\Models\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * @var
     */
    public $user;
    public $userEncode;
    public $standard;
    public $access;

    function __construct(Request $request)
    {
        $this->user = (array_key_exists($request->user, config('settings.customers'))) ? (int)config('settings.customers')[$request->user] : null;
        $this->userEncode = $request->user;
        $this->standard['id'] = (array_key_exists($request->standard, config('settings.standardsSeo'))) ? config('settings.standardsSeo')[$request->standard] : null;
        $this->standard['seo'] = (array_key_exists($request->standard, config('settings.standardsSeo'))) ? $request->standard : null;
        $this->standard['name'] = (array_key_exists($request->standard, config('settings.standardsSeo'))) ? config('settings.standards')[$this->standard['id']] : null;
    }

    public function dashboard(Request $request)
    {
        $user = $this->user;
        $standard = $this->standard;
        $userEncode = $this->userEncode;
        $basicQue = config('settings.standard_basic_ques')[$standard['id']];
        $cssQue = array_keys(config('settings.css_questions'));

        $extraQue = [];
        foreach (config('settings.select_extra_question')[$standard['id']] as $extra)
            $extraQue = array_unique(array_merge($extraQue, $extra));

        $companies = config('settings.customersName');

        if ($user !== null && $standard['id'] !== null) {
            $startOfWeek = Carbon::now()->startOfWeek()->startOfDay()->format('Y-m-d');
            $endOfWeek = Carbon::now()->endOfWeek()->endOfDay()->format('Y-m-d');
            $startOfMonth = Carbon::now()->startOfMonth()->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::now()->endOfMonth()->endOfMonth()->format('Y-m-d');
            $startOfYear = Carbon::now()->startOfYear()->startOfYear()->format('Y-m-d');
            $endOfYear = Carbon::now()->endOfYear()->endOfYear()->format('Y-m-d');

            $data['noOfResponse']['week'] = Respond::select('COUNT(Respond_ID)')
                ->whereExists(function ($query) use ($standard) {
                    $query->select(DB::raw(1))
                        ->from('respons')
                        ->whereColumn('respond.Respond_ID', 'respons.Respons_Respond_ID')
                        ->where('respons.Questions_Standard_ID', $standard['id']);
                })
                ->where('Respond_Customer_ID', $user)
                ->whereDate('created_at', '>=', $startOfWeek)
                ->whereDate('created_at', '<=', $endOfWeek)
                ->count();
            $data['noOfResponse']['month'] = Respond::select('COUNT(Respond_ID)')
                ->whereExists(function ($query) use ($standard) {
                    $query->select(DB::raw(1))
                        ->from('respons')
                        ->whereColumn('respond.Respond_ID', 'respons.Respons_Respond_ID')
                        ->where('respons.Questions_Standard_ID', $standard['id']);
                })
                ->where('Respond_Customer_ID', $user)
                ->whereDate('created_at', '>=', $startOfMonth)
                ->whereDate('created_at', '<=', $endOfMonth)
                ->count();
            $data['noOfResponse']['year'] = Respond::select('COUNT(Respond_ID)')
                ->whereExists(function ($query) use ($standard) {
                    $query->select(DB::raw(1))
                        ->from('respons')
                        ->whereColumn('respond.Respond_ID', 'respons.Respons_Respond_ID')
                        ->where('respons.Questions_Standard_ID', $standard['id']);
                })
                ->where('Respond_Customer_ID', $user)
                ->whereDate('created_at', '>=', $startOfYear)
                ->whereDate('created_at', '<=', $endOfYear)
                ->count();

            $data['sourceCount'] = Response::select(DB::raw('Respons_Source_ID,  COUNT(Respons_Source_ID) as Source_Count'))
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Customer_ID', $user)
                ->groupBy(['Respons_Respond_ID', 'Respons_Source_ID'])
                ->get()
                ->groupBy('Respons_Source_ID')
                ->toArray();

            $monthStrart = Carbon::now()->startOfMonth();
            $monthEnd = $monthStrart->copy()->endOfMonth();

            $monthStrartASC = Carbon::now()->startOfMonth()->subMonth(5);
            $monthEndASC = $monthStrartASC->copy()->endOfMonth();

            $periodStart = $monthStrart->copy();
            $periodEnd = Carbon::now()->endOfMonth();

            /*Customer Suggestion Score Calculation Start*/
            $monthStrartCSS = Carbon::now()->startOfMonth()->subMonth(5);
            $monthEndCSS = $monthStrartCSS->copy()->endOfMonth();
            //            $periodStartCSS = $monthStrartCSS->copy();
            for ($i = 1; $i <= 6; $i++) {
                $data['cssCount']['ans'][$monthStrartCSS->copy()->format('M')] = Response::select(DB::raw('COUNT(Respons_Question_Answer) as total'))
                    ->whereDate('created_at', '>=', $monthStrartCSS->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEndCSS->format('Y-m-d H:i:s'))
                    ->whereIN('Respons_Question_ID', $cssQue)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Question_Answer', 1)
                    ->where('Respons_Customer_ID', $user)
                    ->first()->toArray();

                $data['cssCount']['que'][$monthStrartCSS->copy()->format('M')] = Response::select(DB::raw('COUNT(Respons_Question_Answer) as total'))
                    ->whereDate('created_at', '>=', $monthStrartCSS->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEndCSS->format('Y-m-d H:i:s'))
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->whereIn('Respons_Question_ID', $cssQue)
                    ->where('Respons_Customer_ID', $user)
                    ->first()->toArray();
                $monthStrartCSS = $monthStrartCSS->addMonth();
                $monthEndCSS = $monthStrartCSS->copy()->endOfMonth();
            }

            $data['cssCount']['ans']['total'] = Response::select(DB::raw('COUNT(Respons_Question_Answer) as total'))
                //                ->whereDate('created_at', '>=', $periodStartCSS->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $monthEndCSS->format('Y-m-d H:i:s'))
                ->where('Questions_Standard_ID', $standard['id'])
                ->whereIn('Respons_Question_ID', $cssQue)
                ->where('Respons_Question_Answer', 1)
                ->where('Respons_Customer_ID', $user)
                ->first()->toArray();

            $data['cssCount']['que']['total'] = Response::select(DB::raw('COUNT(Respons_Question_Answer) as total'))
                //                ->whereDate('created_at', '>=', $periodStartCSS->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $monthEndCSS->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $cssQue)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Customer_ID', $user)
                ->first()->toArray();
            //            dd($data['cssCount'], $standard, $user, $cssQue);
            /*Customer Suggestion Score (CSS) Calculation End*/


            $data['responses']['responsesMax'] = 0;
            /* Basic Question Answer Score */
            $basicQuesCountEmpty = true;
            $extraQuesCountEmpty = true;
            for ($i = 1; $i <= 6; $i++) {
                $data['basicQuesCount'][$monthStrart->copy()->format('M')] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, Count(Respons_Question_Answer) as Ans_Count'))
                    ->whereDate('created_at', '>=', $monthStrart->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEnd->format('Y-m-d H:i:s'))
                    ->whereIn('Respons_Question_ID', $basicQue)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Customer_ID', $user)
                    ->where('Respons_Question_Answer', '=', '1')
                    ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                    ->get()
                    ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                    ->toArray();

                $data['extraQuesCount'][$monthStrart->copy()->format('M')] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, Count(Respons_Question_Answer) as Ans_Count'))
                    ->whereDate('created_at', '>=', $monthStrart->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEnd->format('Y-m-d H:i:s'))
                    ->whereIn('Respons_Question_ID', $extraQue)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Customer_ID', $user)
                    ->where('Respons_Question_Answer', '=', '1')
                    ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                    ->get()
                    ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                    ->toArray();

                if (!empty($data['basicQuesCount'][$monthStrart->copy()->format('M')]))
                    $basicQuesCountEmpty = false;

                if (!empty($data['extraQuesCount'][$monthStrart->copy()->format('M')]))
                    $extraQuesCountEmpty = false;

                $data['basicQuesMonthTotalCount'][$monthStrart->copy()->format('M')] = Response::select(DB::raw('Respons_Question_ID, Count(Respons_Question_ID) as Que_Count'))
                    ->whereDate('created_at', '>=', $monthStrart->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEnd->format('Y-m-d H:i:s'))
                    ->whereIn('Respons_Question_ID', $basicQue)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Customer_ID', $user)
                    ->where('Respons_Question_Answer', '!=', '2')
                    ->groupBy(['Respons_Question_ID'])
                    ->get()
                    ->groupBy(['Respons_Question_ID'])
                    ->toArray();

                $data['extraQuesMonthTotalCount'][$monthStrart->copy()->format('M')] = Response::select(DB::raw('Respons_Question_ID, Count(Respons_Question_ID) as Que_Count'))
                    ->whereDate('created_at', '>=', $monthStrart->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEnd->format('Y-m-d H:i:s'))
                    ->whereIn('Respons_Question_ID', $extraQue)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Customer_ID', $user)
                    ->where('Respons_Question_Answer', '!=', '2')
                    ->groupBy(['Respons_Question_ID'])
                    ->get()
                    ->groupBy(['Respons_Question_ID'])
                    ->toArray();

                $data['responses']['data'][$monthStrartASC->copy()->format('M')] = Response::select(DB::raw('Count(Respons_Respond_ID) as Res_Count'))
                    ->whereDate('created_at', '>=', $monthStrartASC->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEndASC->format('Y-m-d H:i:s'))
                    ->where('Respons_Customer_ID', $user)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->groupBy(['Respons_Respond_ID'])
                    ->get();

                $data['responses']['responseCounts'][] = ($data['responses']['data'][$monthStrartASC->copy()->format('M')]) ? $data['responses']['data'][$monthStrartASC->copy()->format('M')]->count() : 0;

                $data['responses']['months_labels'][] = $monthStrartASC->copy()->format('M');

                if (isset($data['responses']['data'][$monthStrartASC->copy()->format('M')]) && ($data['responses']['responsesMax'] < $data['responses']['data'][$monthStrartASC->copy()->format('M')]->count()))
                    $data['responses']['responsesMax'] = $data['responses']['data'][$monthStrartASC->copy()->format('M')]->count();

                $monthStrart = $monthStrart->subMonth();
                $monthEnd = $monthStrart->copy()->endOfMonth();
                $monthStrartASC = $monthStrartASC->addMonth();
                $monthEndASC = $monthStrartASC->copy()->endOfMonth();
            }

            $data['responses']['responsesMax'] = (int)ceil(ceil($data['responses']['responsesMax'] / 10) * 10);
            $data['responses']['count_labels'] = [];
            $i = $data['responses']['responsesMax'] / 10;
            $count = 0;
            if ($data['responses']['responsesMax'] !== 0) {
                while ($count <= $data['responses']['responsesMax']) {
                    $data['responses']['count_labels'][] = $count;
                    $count += $i;
                }
            }

            $data['basicQuesAnsTotalCount']['Total'] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, Count(Respons_Question_Answer) as Ans_Count'))
                //                ->whereDate('created_at', '>=', $monthStrart->copy()->addMonth()->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $periodStart->copy()->endOfMonth()->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $basicQue)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Customer_ID', $user)
                ->where('Respons_Question_Answer', '!=', '2')
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->get()
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->toArray();

            $data['extraQuesAnsTotalCount']['Total'] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, Count(Respons_Question_Answer) as Ans_Count'))
                //                ->whereDate('created_at', '>=', $monthStrart->copy()->addMonth()->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $periodStart->copy()->endOfMonth()->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $extraQue)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Customer_ID', $user)
                ->where('Respons_Question_Answer', '!=', '2')
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->get()
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->toArray();

            $data['basicQuesTotalCount']['Total'] = Response::select(DB::raw('Respons_Question_ID, Count(Respons_Question_ID) as Que_Count'))
                //                ->whereDate('created_at', '>=', $monthStrart->copy()->addMonth()->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $periodStart->copy()->endOfMonth()->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $basicQue)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Customer_ID', $user)
                ->where('Respons_Question_Answer', '!=', '2')
                ->groupBy(['Respons_Question_ID'])
                ->get()
                ->groupBy(['Respons_Question_ID'])
                ->toArray();

            $data['extraQuesTotalCount']['Total'] = Response::select(DB::raw('Respons_Question_ID, Count(Respons_Question_ID) as Que_Count'))
                //                ->whereDate('created_at', '>=', $monthStrart->copy()->addMonth()->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $periodStart->copy()->endOfMonth()->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $extraQue)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Customer_ID', $user)
                ->where('Respons_Question_Answer', '!=', '2')
                ->groupBy(['Respons_Question_ID'])
                ->get()
                ->groupBy(['Respons_Question_ID'])
                ->toArray();

            return view('front.dashboard', compact('data', 'userEncode', 'user', 'basicQuesCountEmpty', 'extraQuesCountEmpty', 'companies', 'standard'));
        } else abort(404);
    }


    public function dashboard2(Request $request)
    {
        $user = $this->user;
        $standard = $this->standard;
        $userEncode = $this->userEncode;
        $companies = config('settings.customersName');

        if ($user !== null && $standard['id'] !== null) {
            $standardsQueListTmp = array_merge_recursive(config('settings.select')[$standard['id']]);
            $data['questionsList'] = [];
            foreach ($standardsQueListTmp as $standardTemp) {
                foreach ($standardTemp as $temp) $data['questionsList'][$temp] = config('settings.questions')[$temp];
            }
            ksort($data['questionsList']);

            $queArray = array_keys($data['questionsList']);
            $monthStrart = Carbon::now()->startOfMonth();
            $monthEnd = $monthStrart->copy()->endOfMonth();
            $periodStart = $monthStrart->copy();
            for ($i = 1; $i <= 6; $i++) {
                $data['queAnsAvg'][$monthStrart->format('M')] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, COUNT(Respons_Question_Answer) as Ans_Avg'))
                    ->whereIn('Respons_Question_ID', $queArray)
                    ->where('Respons_Customer_ID', $user)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Question_Answer', '!=', '2')
                    ->whereDate('created_at', '>=', $monthStrart->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEnd->format('Y-m-d H:i:s'))
                    ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                    ->get()
                    ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                    ->toArray();

                $data['queCount'][$monthStrart->format('M')] = Response::select(DB::raw('Respons_Question_ID, COUNT(Respons_Question_ID) as queCount'))
                    ->whereIn('Respons_Question_ID', $queArray)
                    ->where('Respons_Customer_ID', $user)
                    ->where('Questions_Standard_ID', $standard['id'])
                    ->where('Respons_Question_Answer', '!=', '2')
                    ->whereDate('created_at', '>=', $monthStrart->format('Y-m-d H:i:s'))
                    ->whereDate('created_at', '<=', $monthEnd->format('Y-m-d H:i:s'))
                    ->groupBy(['Respons_Question_ID'])
                    ->get()
                    ->groupBy(['Respons_Question_ID'])
                    ->toArray();
                $monthStrart = $monthStrart->subMonth()->startOfMonth();
                $monthEnd = $monthStrart->copy()->endOfMonth();
            }

            $data['standardQuesAnsTotalCount']['Total'] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, Count(Respons_Question_Answer) as Ans_Count'))
                //                ->whereDate('created_at', '>=', $monthStrart->copy()->addMonth()->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $periodStart->copy()->endOfMonth()->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $queArray)
                ->where('Respons_Customer_ID', $user)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Question_Answer', '!=', '2')
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->get()
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->toArray();

            $data['standardQuesTotalCount']['Total'] = Response::select(DB::raw('Respons_Question_ID, Count(Respons_Question_ID) as Que_Count'))
                //                ->whereDate('created_at', '>=', $monthStrart->copy()->addMonth()->format('Y-m-d H:i:s'))
                //                ->whereDate('created_at', '<=', $periodStart->copy()->endOfMonth()->format('Y-m-d H:i:s'))
                ->whereIn('Respons_Question_ID', $queArray)
                ->where('Respons_Customer_ID', $user)
                ->where('Questions_Standard_ID', $standard['id'])
                ->where('Respons_Question_Answer', '!=', '2')
                ->groupBy(['Respons_Question_ID'])
                ->get()
                ->groupBy(['Respons_Question_ID'])
                ->toArray();

            //        dd($data);
            return view('front.dashboard2', compact('data', 'user', 'userEncode', 'companies', 'standard'));
        } else abort(404);
    }


    public function dashboard3(Request $request)
    {
        $user = $this->user;
        $standard = $this->standard;
        $userEncode = $this->userEncode;
        $cssQue = array_keys(config('settings.css_questions'));
        $companies = config('settings.customersName');

        if ($user !== null && $standard['id'] !== null) {
            $data['responds'] = Respond::select('Respond_ID', 'Respond_IP', 'Respond_Customer_ID', 'Respond_Customer_ID', 'Respond_Email', 'Respond_OtherInfo', 'Respond_Contact', 'Respond_Name', 'Respond_Phone', 'respond.created_at', 'res.Respons_Question_ID', 'res.Respons_Question_Answer')
                ->join('respons as res', function ($join) {
                    $join->on('res.Respons_Respond_ID', '=', 'Respond_ID');
                })
                ->whereIn('res.Respons_Question_ID', $cssQue)
                ->where('Respond_Customer_ID', $user)
                ->where('res.Questions_Standard_ID', $standard['id'])
                ->whereNotNull('Respond_OtherInfo')
                ->where('Respond_OtherInfo', '!=', '')
                ->orderBy('respond.created_at', 'DESC')
                ->paginate(50);
            //        dd($data);
            return view('front.dashboard3', compact('data', 'userEncode', 'user', 'companies', 'standard'));
        } else abort(404);
    }


    public function dashboard4(Request $request)
    {
        $user = $this->user;
        $standard = $this->standard;
        $userEncode = $this->userEncode;
        $companies = config('settings.customersName');

        if ($user !== null) {
            $standardsQueListTmp = array_merge_recursive(config('settings.select')[$standard['id']]);
            $data['questionsList'] = [];
            foreach ($standardsQueListTmp as $standardTemp) {
                foreach ($standardTemp as $temp) $data['questionsList'][$temp] = config('settings.questions')[$temp];
            }
            ksort($data['questionsList']);

            $queArray = array_keys($data['questionsList']);
            $data['queAnsCount'] = Response::select(DB::raw('Respons_Question_ID, Respons_Question_Answer, COUNT(Respons_Question_Answer) as Ans_Count'))
                ->whereIn('Respons_Question_ID', $queArray)
                ->where('Respons_Customer_ID', (int)$user)
                ->where('Questions_Standard_ID', $standard['id'])
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->get()
                ->groupBy(['Respons_Question_ID', 'Respons_Question_Answer'])
                ->toArray();

            //        dd($data);
            return view('front.dashboard4', compact('data', 'user', 'userEncode', 'companies', 'standard'));
        } else abort(404);
    }

    public function changeNPS()
    {
        $nps = Response::where('Respons_Question_ID', 1001)->get();
        foreach ($nps as $np) {
            if ($np->Respons_Question_Answer < 7 && $np->Respons_Question_Answer >= 0) :
                Response::where('Respons_ID', $np->Respons_ID)->update(['Respons_Question_Answer' => 0]);
            elseif ($np->Respons_Question_Answer <= 10 && $np->Respons_Question_Answer >= 7) :
                Response::where('Respons_ID', $np->Respons_ID)->update(['Respons_Question_Answer' => 1]);
            endif;
        }
    }
}
