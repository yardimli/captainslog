<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\LogBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LogBlockController extends Controller
{
    public function store(Request $request, DailyLog $dailyLog)
    {
        abort_unless($dailyLog->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'type' => 'required|in:text',
            'content' => 'required|string|max:100000',
            'occurred_at' => 'nullable|date_format:H:i',
        ]);
        $data['occurred_at'] = $dailyLog->log_date->copy()->setTimeFromTimeString($data['occurred_at'] ?? now()->format('H:i'));
        $block = $dailyLog->blocks()->create($data + ['position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);

        return response()->json(['message' => 'Entry added.', 'block' => $block, 'reload' => true], 201);
    }

    public function update(Request $request, LogBlock $block)
    {
        $this->authorizeBlock($request, $block);
        abort_if($block->type === 'event', 422, 'Edit event notes from the event editor.');
        $data = $request->validate([
            'content' => 'nullable|string|max:100000',
            'occurred_at' => 'sometimes|required|date_format:H:i',
        ]);
        if (isset($data['occurred_at'])) {
            $data['occurred_at'] = $block->dailyLog->log_date->copy()->setTimeFromTimeString($data['occurred_at']);
        }
        $block->update($data);

        return response()->json(['message' => 'Entry updated.', 'block' => $block->fresh()]);
    }

    public function destroy(Request $request, LogBlock $block)
    {
        $this->authorizeBlock($request, $block);
        $block->attachments->each(function ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });
        $block->delete();

        return response()->json(['message' => 'Entry deleted.']);
    }

    private function authorizeBlock(Request $request, LogBlock $block): void
    {
        abort_unless($block->dailyLog()->where('user_id', $request->user()->id)->exists(), 403);
    }
}
