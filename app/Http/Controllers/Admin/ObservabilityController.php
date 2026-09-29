<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\AuditPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;

class ObservabilityController extends Controller
{
    /** Security-log event name → what it means, and how loud it is. */
    private const SECURITY_EVENTS = [
        'auth.login_success' => ['label' => 'Masuk ke panel', 'tone' => 'success'],
        'auth.login_failed' => ['label' => 'Percobaan masuk gagal', 'tone' => 'warning'],
        'documents.download_authorized' => ['label' => 'Surat diunduh warga', 'tone' => 'info'],
        'documents.download_denied' => ['label' => 'Unduhan surat ditolak (kode/NIK salah)', 'tone' => 'warning'],
        'upload.scanner_rejected' => ['label' => 'Berkas ditolak pemindai', 'tone' => 'danger'],
        'upload.signature_rejected' => ['label' => 'Berkas ditolak: tanda tangan berbahaya', 'tone' => 'danger'],
        'whatsapp.send_failed' => ['label' => 'Pesan WhatsApp gagal terkirim', 'tone' => 'warning'],
        'whatsapp.document_send_failed' => ['label' => 'Dokumen WhatsApp gagal terkirim', 'tone' => 'warning'],
    ];

    public function activityLogs(Request $request)
    {
        $subjectClass = null;
        if ($request->filled('subject') && array_key_exists($request->string('subject')->toString(), AuditPresenter::SUBJECTS)) {
            $basename = $request->string('subject')->toString();
            $subjectClass = $basename === 'Role' ? \Spatie\Permission\Models\Role::class : 'App\\Models\\'.$basename;
        }

        $activities = Activity::query()
            ->with('causer')
            ->where('log_name', 'business-model')
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
            ->when($subjectClass, fn ($query) => $query->where('subject_type', $subjectClass))
            ->when($request->filled('user'), fn ($query) => $query->where('causer_id', $request->integer('user')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($q) => $q->where('description', 'like', $term)->orWhere('properties', 'like', $term));
            })
            // Rows written in the same second keep insertion order: newest first.
            ->latest()->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.observability.activity-logs', [
            'activities' => $activities,
            'days' => $activities->getCollection()->groupBy(fn (Activity $activity) => $activity->created_at->toDateString()),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'subjects' => AuditPresenter::subjectOptions(),
            'filters' => $request->only(['q', 'event', 'subject', 'user', 'from', 'to']),
            'todayCount' => Activity::where('log_name', 'business-model')->whereDate('created_at', today())->count(),
        ]);
    }

    public function notificationLogs(Request $request)
    {
        $logs = NotificationLog::query()
            ->with('serviceRequest:id,request_code,applicant_name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($q) => $q->where('recipient', 'like', $term)
                    ->orWhereHas('serviceRequest', fn ($r) => $r->where('request_code', 'like', $term)->orWhere('applicant_name', 'like', $term)));
            })
            ->latest()->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.observability.notification-logs', [
            'logs' => $logs,
            'counts' => NotificationLog::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /**
     * The security channel is a daily log, so the file for each day is read separately.
     * The newest lines across the last few days are parsed into rows a person can read.
     */
    public function securityLogs(Request $request)
    {
        $files = collect(File::glob(storage_path('logs/security*.log')))
            ->sortDesc()
            ->take(7);

        $rows = collect();
        foreach ($files as $file) {
            $lines = array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
            foreach ($lines as $line) {
                if ($row = $this->parseSecurityLine($line)) {
                    $rows->push($row);
                }
                if ($rows->count() >= 400) {
                    break 2;
                }
            }
        }

        if ($request->filled('event')) {
            $rows = $rows->where('event', $request->string('event')->toString());
        }
        $rows = $rows->take(200)->values();

        $userIds = $rows->pluck('context.user_id')->filter()->unique();
        $requestIds = $rows->pluck('context.service_request_id')->filter()->unique();

        return view('admin.observability.security-logs', [
            'rows' => $rows,
            'events' => collect(self::SECURITY_EVENTS)->map(fn ($meta) => $meta['label']),
            'userNames' => $userIds->isEmpty() ? collect() : User::whereIn('id', $userIds)->pluck('name', 'id'),
            'requestCodes' => $requestIds->isEmpty() ? collect() : ServiceRequest::whereIn('id', $requestIds)->pluck('request_code', 'id'),
            'filters' => $request->only(['event']),
        ]);
    }

    /** @return array{time: Carbon, level: string, event: string, label: string, tone: string, context: array}|null */
    private function parseSecurityLine(string $line): ?array
    {
        if (! preg_match('/^\[([^\]]+)\] \w+\.(\w+): (\S+)(?: (\{.*\}))?/', $line, $match)) {
            return null;
        }
        $meta = self::SECURITY_EVENTS[$match[3]] ?? ['label' => $match[3], 'tone' => $match[2] === 'INFO' ? 'muted' : 'warning'];

        try {
            $time = Carbon::parse($match[1]);
        } catch (\Throwable) {
            return null;
        }

        return [
            'time' => $time,
            'level' => $match[2],
            'event' => $match[3],
            'label' => $meta['label'],
            'tone' => $meta['tone'],
            'context' => json_decode($match[4] ?? '{}', true) ?: [],
        ];
    }
}
