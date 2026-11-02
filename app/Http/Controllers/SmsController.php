<?php

namespace App\Http\Controllers;

use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Throwable;

class SmsController extends Controller
{
    public function index()
    {
        return view('sms.index');
    }

    public function send(Request $request, SmsGatewayService $smsGateway)
    {
        $request->merge([
            'destination' => preg_replace('/\D+/', '', (string) $request->input('destination')),
        ]);

        $data = $request->validate([
            'destination' => ['required', 'digits_between:8,15'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $result = $smsGateway->send($data['destination'], $data['message']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['sms' => $exception->getMessage()]);
        }

        return back()
            ->with('success', 'The SMS gateway request completed.')
            ->with('sms_result', $result);
    }
}
