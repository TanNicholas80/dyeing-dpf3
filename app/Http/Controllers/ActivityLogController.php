<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Activity::with('causer', 'subject');

            $user = Auth::user();
            if ($user && in_array($user->role, ['fm', 'vp'])) {
                $query->where('log_name', '!=', 'Manajemen User');
            }

            return \Yajra\DataTables\Facades\DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('user', function ($activity) {
                    if ($activity->causer) {
                        return '<code>' . htmlspecialchars($activity->causer->username ?? $activity->causer->name ?? 'N/A') . '</code>';
                    }
                    return '<span class="text-muted">System</span>';
                })
                ->editColumn('log_name', function ($activity) {
                    if ($activity->log_name) {
                        return '<span class="badge bg-info">' . htmlspecialchars($activity->log_name) . '</span>';
                    }
                    return '<span class="text-muted">-</span>';
                })
                ->editColumn('event', function ($activity) {
                    $event = $activity->event ?? 'unknown';
                    $badgeClass = match($event) {
                        'created' => 'badge bg-success',
                        'updated' => 'badge bg-warning text-dark',
                        'deleted' => 'badge bg-danger',
                        default => 'badge bg-secondary'
                    };
                    return '<span class="' . $badgeClass . '">' . ucfirst($event) . '</span>';
                })
                ->editColumn('properties', function ($activity) {
                    $properties = $activity->properties ?? [];
                    // Laravel converts it to collection, so we turn it back to array or count
                    $propsArray = $properties instanceof \Illuminate\Support\Collection ? $properties->toArray() : $properties;

                    $hasBefore = !empty($propsArray['before_update']);
                    $hasAfter = !empty($propsArray['after_update']) || !empty($propsArray['created_data']) || !empty($propsArray['deleted_data']);

                    $html = '<div class="d-flex">';
                    if ($hasBefore) {
                        $content = htmlspecialchars(json_encode($propsArray['before_update']), ENT_QUOTES, 'UTF-8');
                        $html .= '<button type="button" class="btn btn-sm btn-outline-info mr-2" data-content=\'' . $content . '\' data-title="Data Sebelum Update" onclick="showDataModal(this)"><i class="fas fa-eye"></i> Before</button>';
                    }
                    if ($hasAfter) {
                        $afterData = $propsArray['after_update'] ?? $propsArray['created_data'] ?? $propsArray['deleted_data'] ?? null;
                        $afterTitle = isset($propsArray['after_update']) ? 'Data Setelah Update' : (isset($propsArray['created_data']) ? 'Data yang Dibuat' : 'Data yang Dihapus');
                        $content = htmlspecialchars(json_encode($afterData), ENT_QUOTES, 'UTF-8');
                        $html .= '<button type="button" class="btn btn-sm btn-outline-success mr-2" data-content=\'' . $content . '\' data-title="' . $afterTitle . '" onclick="showDataModal(this)"><i class="fas fa-eye"></i> After</button>';
                    }
                    if (!$hasBefore && !$hasAfter && !empty($propsArray)) {
                        $content = htmlspecialchars(json_encode($propsArray), ENT_QUOTES, 'UTF-8');
                        $html .= '<button type="button" class="btn btn-sm btn-outline-primary" data-content=\'' . $content . '\' data-title="Properties" onclick="showDataModal(this)"><i class="fas fa-info-circle"></i> View</button>';
                    }
                    if (!$hasBefore && !$hasAfter && empty($propsArray)) {
                        $html .= '<span class="text-muted">-</span>';
                    }
                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('created_at', function ($activity) {
                    return $activity->created_at ? $activity->created_at->format('d-m-Y H:i:s') : '-';
                })
                ->rawColumns(['user', 'log_name', 'event', 'properties', 'created_at'])
                ->make(true);
        }

        return view('activity_log.index');
    }
}
