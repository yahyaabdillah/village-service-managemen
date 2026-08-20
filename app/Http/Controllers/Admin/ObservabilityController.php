<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;

class ObservabilityController extends Controller
{
    public function activityLogs(Request $request)
    {
        $activities = Activity::query()
            ->when($request->filled('log_name'), fn ($query) => $query->where('log_name', $request->string('log_name')))
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
            ->when($request->filled('q'), fn ($query) => $query->where('description', 'like', '%'.$request->string('q')->toString().'%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.observability.activity-logs', [
            'activities' => $activities,
            'filters' => $request->only(['log_name', 'event', 'q']),
        ]);
    }

    public function notificationLogs(Request $request)
    {
        $logs = NotificationLog::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($query) => $query->where('recipient', 'like', '%'.$request->string('q')->toString().'%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.observability.notification-logs', ['logs' => $logs]);
    }

    public function securityLogs()
    {
        $path = storage_path('logs/security.log');
        $lines = File::exists($path) ? array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -200) : [];

        return view('admin.observability.security-logs', [
            'lines' => array_reverse($lines),
        ]);
    }
}
