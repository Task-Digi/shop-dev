<?php

namespace App\Listeners;

use App\Models\Respond;
use App\Models\Response;
use App\Events\SendSurveyMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSurveyMailListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param SendSurveyMail $event
     * @return void
     */
    public function handle(SendSurveyMail $event)
    {
        if (isset($event->respond)) {
            $data['respond'] = Respond::where('Respond_ID', $event->respond)->first();
            if (isset($data['respond']) && $data['respond'] !== null) {
                $data['status'] = 200;
                $data['respons'] = Response::where('Respons_Respond_ID', $data['respond']->Respond_ID)->get()->groupBy('Respons_Question_ID')->toArray();
            } else {
                $data['status'] = 404;
            }
            Mail::send(['html' => 'front.mail'], ['data' => $data, 'mail' => true], function ($message) {
                //                $message->to('task@portu.no', 'QUESTIFY FARGERIKE');
                //                $message->subject('QUESTIFY FARGERIKE Mail Notification');
                //                $message->from('anushanv92@gmail.com','QUESTIFY FARGERIKE');
                //                $message->from('torkel.ruud@portu.no','Torkel Ruud');
                $message->from('task@portu.no', 'QUESTIFY FARGERIKE');
                $message->to('post@multico.no', 'Torkel Ruud');
                $message->subject('QUESTIFY FARGERIKE Mail Notification');
                //                $message->from('anu92v@gmail.com','Torkel Ruud');
            });
        }
    }
}
