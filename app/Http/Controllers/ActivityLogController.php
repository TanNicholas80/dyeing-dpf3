<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function index()
    {
        $query = Activity::with('causer', 'subject')
            ->orderBy('created_at', 'desc');

        // Jika role FM atau VP, sembunyikan log dengan nama "Manajemen User"
        $user = Auth::user();
        if ($user && in_array($user->role, ['fm', 'vp'])) {
            $query->where('log_name', '!=', 'Manajemen User');
        }

        $activities = $query->get();

        return view('activity_log.index', compact('activities'));
    }
}
