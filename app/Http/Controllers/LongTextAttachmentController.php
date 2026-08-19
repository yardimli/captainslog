<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\LongTextAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LongTextAttachmentController extends Controller
{
    public function store(Request $request, DailyLog $dailyLog)
    {
        abort_unless($dailyLog->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000000'],
            'format' => ['required', 'in:text,markdown'],
            'block_id' => ['nullable', 'integer'],
            'occurred_at' => ['nullable', 'date_format:H:i'],
        ]);
        $occurredAt = $dailyLog->log_date->copy()->setTimeFromTimeString($data['occurred_at'] ?? now()->format('H:i'));

        [$longText, $created] = DB::transaction(function () use ($request, $dailyLog, $data, $occurredAt) {
            $block = empty($data['block_id'])
                ? $dailyLog->blocks()->create([
                    'type' => 'long_text', 'emoji' => '📄',
                    'position' => ((int) $dailyLog->blocks()->max('position')) + 1,
                    'occurred_at' => $occurredAt,
                ])
                : LogBlock::findOrFail($data['block_id']);
            abort_unless($block->daily_log_id === $dailyLog->id, 403);
            $block->update(['occurred_at' => $occurredAt]);
            $block->taskEvent?->update(['occurred_at' => $occurredAt]);

            $longText = LongTextAttachment::updateOrCreate(
                ['log_block_id' => $block->id],
                ['user_id' => $request->user()->id, 'daily_log_id' => $dailyLog->id, 'format' => $data['format'], 'content' => $data['content']],
            );

            return [$longText, $longText->wasRecentlyCreated];
        });

        return response()->json([
            'message' => $created ? 'Long text attached.' : 'Long text replaced.',
            'long_text' => $longText,
            'reload' => true,
        ], $created ? 201 : 200);
    }
}
