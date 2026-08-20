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
            ->get(['created_at', 'status', 'service_type_id']);
        $trendLabels = collect(range(0, 6))
            ->map(fn (int $offset) => $startDate->copy()->addDays($offset));
        $statusBreakdown = ServiceRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $serviceBreakdown = ServiceType::query()
            ->withCount('requests')
            ->orderByDesc('requests_count')
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'totalResidents' => Resident::count(),
            'totalRequests' => ServiceRequest::count(),
            'newRequests' => ServiceRequest::where('status', 'submitted')->count(),
            'processingRequests' => ServiceRequest::whereIn('status', ['verified', 'processing'])->count(),
            'completedRequests' => ServiceRequest::where('status', 'completed')->count(),
            'rejectedRequests' => ServiceRequest::where('status', 'rejected')->count(),
            'serviceTypes' => ServiceType::count(),
            'generatedDocuments' => GeneratedDocument::count(),
            'latestRequests' => ServiceRequest::with('serviceType')->latest()->take(8)->get(),
            'trendLabels' => $trendLabels->map(fn (Carbon $date) => $date->translatedFormat('D'))->values(),
            'trendData' => $trendLabels->map(
                fn (Carbon $date) => $requestsInPeriod->filter(
                    fn (ServiceRequest $request) => $request->created_at->isSameDay($date)
                )->count()
            )->values(),
            'statusBreakdown' => $statusBreakdown,
            'serviceBreakdown' => $serviceBreakdown,
        ]);
    }
}
