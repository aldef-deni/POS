<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->query('action').'%'))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->query('user')))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('dashboard.activity.index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'user']),
        ]);
    }
}
