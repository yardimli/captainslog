<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\DailyLog;
use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenRouterController extends Controller
{
    public function __construct(private OpenRouterService $openRouter) {}

    public function models(Request $request)
    {
        return response()->json(['data' => $this->openRouter->models($request->user(), $request->boolean('images'))]);
    }

    public function chat(Request $request, DailyLog $dailyLog)
    {
        abort_unless($dailyLog->user_id === $request->user()->id, 403);
        $data = $request->validate(['message' => 'required|string|max:30000', 'model' => 'required|string|max:200', 'attachment_ids' => 'array|max:8', 'attachment_ids.*' => 'integer']);
        $userBlock = $dailyLog->blocks()->create(['type' => 'chat_user', 'content' => $data['message'], 'metadata' => ['model' => $data['model']], 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);
        $attachments = Attachment::where('user_id', $request->user()->id)->where('daily_log_id', $dailyLog->id)->whereIn('id', $data['attachment_ids'] ?? [])->get();
        $content = [['type' => 'text', 'text' => $data['message']]];
        foreach ($attachments->where('type', 'image') as $attachment) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$attachment->mime_type.';base64,'.base64_encode(Storage::disk($attachment->disk)->get($attachment->path))]];
            $attachment->update(['log_block_id' => $userBlock->id]);
        }
        $messages = $dailyLog->blocks()->whereIn('type', ['chat_user', 'chat_assistant'])->where('id', '<', $userBlock->id)->latest('id')->limit(20)->get()->reverse()->map(fn ($item) => [
            'role' => $item->type === 'chat_assistant' ? 'assistant' : 'user', 'content' => $item->content,
        ])->values()->all();
        $messages[] = ['role' => 'user', 'content' => $content];
        $result = $this->openRouter->chat($request->user(), $dailyLog, $userBlock, $data['model'], $messages);
        $assistant = $dailyLog->blocks()->create(['type' => 'chat_assistant', 'content' => data_get($result, 'choices.0.message.content', ''), 'metadata' => ['model' => $result['model'] ?? $data['model']], 'position' => $userBlock->position + 1]);

        return response()->json(['message' => 'Reply added to the log.', 'block' => $assistant, 'reload' => true], 201);
    }

    public function image(Request $request, DailyLog $dailyLog)
    {
        abort_unless($dailyLog->user_id === $request->user()->id, 403);
        $data = $request->validate(['prompt' => 'required|string|max:5000', 'model' => 'required|string|max:200']);
        $block = $dailyLog->blocks()->create(['type' => 'generated_image', 'content' => $data['prompt'], 'metadata' => ['model' => $data['model']], 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);
        $result = $this->openRouter->image($request->user(), $dailyLog, $block, $data['model'], $data['prompt']);
        $encoded = data_get($result, 'data.0.b64_json');
        if (! $encoded) {
            abort(502, 'OpenRouter did not return image data.');
        }
        $path = "users/{$request->user()->id}/{$dailyLog->log_date->format('Y/m/d')}/generated-".Str::uuid().'.png';
        $encoded = str_contains($encoded, ',') ? Str::after($encoded, ',') : $encoded;
        Storage::disk('local')->put($path, base64_decode($encoded));
        Attachment::create(['user_id' => $request->user()->id, 'daily_log_id' => $dailyLog->id, 'log_block_id' => $block->id, 'type' => 'image', 'disk' => 'local', 'path' => $path, 'original_name' => 'generated.png', 'mime_type' => 'image/png', 'size' => Storage::disk('local')->size($path), 'metadata' => ['generated' => true, 'prompt' => $data['prompt']]]);

        return response()->json(['message' => 'Image generated and added.', 'reload' => true], 201);
    }

    public function transcribe(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->user_id === $request->user()->id && $attachment->type === 'audio', 403);
        $data = $request->validate(['model' => 'required|string|max:200']);
        $format = match ($attachment->mime_type) {
            'audio/wav', 'audio/x-wav' => 'wav', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', default => 'webm'
        };
        $block = $attachment->logBlock ?: $attachment->dailyLog->blocks()->create(['type' => 'media', 'position' => ($attachment->dailyLog->blocks()->max('position') ?? 0) + 1]);
        $result = $this->openRouter->transcribe($request->user(), $attachment->dailyLog, $block, $data['model'], base64_encode(Storage::disk($attachment->disk)->get($attachment->path)), $format);
        $text = $result['text'] ?? '';
        $block->update(['content' => trim(($block->content ? $block->content."\n\n" : '')."Transcript:\n".$text), 'metadata' => array_merge($block->metadata ?? [], ['transcription_model' => $data['model']])]);

        return response()->json(['message' => 'Transcript added.', 'text' => $text, 'reload' => true]);
    }
}
