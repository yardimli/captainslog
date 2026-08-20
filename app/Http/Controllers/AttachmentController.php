<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->user_id === $request->user()->id, 403);
        abort_unless($attachment->logBlock?->type === 'generated_image', 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->file(Storage::disk($attachment->disk)->path($attachment->path), ['Content-Type' => $attachment->mime_type]);
    }
}
