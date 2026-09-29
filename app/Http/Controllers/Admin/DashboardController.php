<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $startDate = now()->startOfDay()->subDays(6);
        $requestsInPeriod = ServiceRequest::query()
            ->where('created_at', '>=', $startDate)
            ->get(['created_at']);
        $trendDays = collect(range(0, 6))->map(fn (int $offset) => $startDate->copy()->addDays($offset));
        $statusBreakdown = ServiceRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.dashboard', [
            'totalResidents' => Resident::count(),
            'totalRequests' => ServiceRequest::count(),
            'newRequests' => (int) ($statusBreakdown['submitted'] ?? 0),
            'processingRequests' => (int) ($statusBreakdown['verified'] ?? 0) + (int) ($statusBreakdown['processing'] ?? 0),
            'completedRequests' => (int) ($statusBreakdown['completed'] ?? 0),
            'completedThisMonth' => ServiceRequest::where('status', 'completed')->where('completed_at', '>=', now()->startOfMonth())->count(),
            'oldestWaitingDays' => ($oldest = ServiceRequest::where('status', 'submitted')->min('created_at')) ? (int) floor(Carbon::parse($oldest)->diffInDays(now())) : null,
            'activeServices' => ServiceType::where('is_active', true)->count(),
            'generatedDocuments' => GeneratedDocument::where('is_active', true)->count(),
            'latestRequests' => ServiceRequest::with('serviceType')->latest()->take(8)->get(),
            'topServices' => ServiceType::withCount('requests')->orderByDesc('requests_count')->take(4)->get(),
            'trendLabels' => $trendDays->map(fn (Carbon $date) => $date->translatedFormat('D'))->values(),
            'trendData' => $trendDays->map(
                fn (Carbon $date) => $requestsInPeriod->filter(fn (ServiceRequest $request) => $request->created_at->isSameDay($date))->count()
            )->values(),
            'weekTotal' => $requestsInPeriod->count(),
            'statusBreakdown' => $statusBreakdown,
        ]);
    }
}
