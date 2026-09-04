<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GuestDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private GuestDemoService $demo) {}

    public function users(): View
    {
        $users = User::query()
            ->withCount(['dailyLogs', 'taskDefinitions'])
            ->orderByDesc('created_at')
            ->paginate(50);
        $demoUserCount = User::where('is_guest', true)->count();

        return view('admin.users', compact('users', 'demoUserCount'));
    }

    public function destroyDemoData(): RedirectResponse
    {
        $this->demo->reset();

        return redirect()->route('admin.users')->with('status', 'Demo data reset around today.');
    }
}
