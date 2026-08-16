<?php

namespace App\Http\Controllers;

use App\Models\ApiCall;
use Illuminate\Http\Request;

class ApiUsageController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = ApiCall::query()->where('user_id', $request->user()->id);
        $totals = (clone $baseQuery)->selectRaw('COUNT(*) as calls, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost')->first();
        $calls = $baseQuery->with('dailyLog')->latest()->paginate(50);

        return view('api-usage.index', compact('calls', 'totals'));
    }
}
