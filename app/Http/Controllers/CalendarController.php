<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function showCalendar()
    {
        // Black Friday offer dates
        $startDate = '2023-12-01';
        $endDate = '2023-12-24';

        // Generate an array of dates
        $dates = [];
        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $dates[] = $currentDate;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' + 1 day'));
        }

        return view('coupon/Calendar', compact('dates'));
    }
}
