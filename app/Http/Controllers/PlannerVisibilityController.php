<?php

namespace App\Http\Controllers;

use App\Models\LogBlock;
use Illuminate\Http\Request;

class PlannerVisibilityController extends Controller
{
    public function block(Request $request, LogBlock $block)
    {
        abort_unless($block->dailyLog()->where('user_id', $request->user()->id)->exists(), 403);
        $data = $request->validate(['hidden' => 'required|boolean']);
        $block->update(['is_hidden' => $data['hidden']]);

        return response()->json(['message' => $data['hidden'] ? 'Entry hidden from this day.' : 'Entry restored to this day.', 'reload' => true]);
    }
}
