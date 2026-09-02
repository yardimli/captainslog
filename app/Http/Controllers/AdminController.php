<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
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
        $deleted = DB::transaction(fn () => User::where('is_guest', true)->delete());

        return redirect()->route('admin.users')->with('status', $deleted
            ? "Deleted {$deleted} demo ".($deleted === 1 ? 'user' : 'users').' and all associated demo data.'
            : 'There was no demo data to delete.');
    }
}
