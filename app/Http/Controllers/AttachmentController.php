<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\LogBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    public function store(Request $request, DailyLog $dailyLog)
    {
        abort_unless($dailyLog->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'file' => 'required|file|max:102400|mimetypes:image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/wav,audio/webm,audio/ogg,audio/mp4,video/mp4,video/webm,video/quicktime',
            'block_id' => 'nullable|integer',
            'occurred_at' => 'nullable|date_format:H:i',
        ]);
        $file = $data['file'];
        $type = Str::before($file->getMimeType(), '/');
        $occurredAt = $dailyLog->log_date->copy()->setTimeFromTimeString($data['occurred_at'] ?? now()->format('H:i'));
        $mediaEmoji = match ($type) {
            'image' => '🖼️',
            'audio' => '🎙️',
            'video' => '🎥',
            default => LogBlock::defaultEmojiForType('media'),
        };
        $block = empty($data['block_id']) ? $dailyLog->blocks()->create(['type' => 'media', 'emoji' => $mediaEmoji, 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1, 'occurred_at' => $occurredAt]) : LogBlock::findOrFail($data['block_id']);
        abort_unless($block->daily_log_id === $dailyLog->id, 403);
        $block->update(['occurred_at' => $occurredAt]);
        $block->taskEvent?->update(['occurred_at' => $occurredAt]);
        $path = $file->store("users/{$request->user()->id}/{$dailyLog->log_date->format('Y/m/d')}");
        $attachment = Attachment::create([
            'user_id' => $request->user()->id, 'daily_log_id' => $dailyLog->id, 'log_block_id' => $block->id,
            'type' => $type, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);

        return response()->json(['message' => ucfirst($type).' attached.', 'attachment' => $attachment, 'reload' => true], 201);
    }

    public function show(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->file(Storage::disk($attachment->disk)->path($attachment->path), ['Content-Type' => $attachment->mime_type]);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->user_id === $request->user()->id, 403);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $block = $attachment->logBlock;
        $attachment->delete();
        if ($block && $block->type === 'media' && ! $block->attachments()->exists()) {
            $block->delete();
        }

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
