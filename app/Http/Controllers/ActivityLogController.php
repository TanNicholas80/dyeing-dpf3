<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index()
    {
        $activities = Activity::with('causer', 'subject')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('activity_log.index', compact('activities'));
    }
}
