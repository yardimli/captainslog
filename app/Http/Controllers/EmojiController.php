<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmojiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $groups = $this->groups();
        $categories = $groups->map(fn (array $group) => [
            'name' => $group['name'],
            'slug' => $group['slug'],
            'count' => count($group['emojis'] ?? []),
        ])->values();
        $query = mb_strtolower(trim($data['q'] ?? ''));
        $present = fn ($emojis) => collect($emojis)->map(fn (array $emoji) => [
            'emoji' => $emoji['emoji'],
            'name' => $emoji['name'],
            'slug' => $emoji['slug'],
        ])->values();

        if ($query !== '') {
            $queryWords = collect(preg_split('/[^\p{L}\p{N}]+/u', $query))->filter()->values();
            $matches = $groups->flatMap(fn (array $group) => $group['emojis'] ?? [])
                ->filter(function (array $emoji) use ($query, $queryWords) {
                    if ($queryWords->isEmpty()) {
                        return str_contains((string) ($emoji['emoji'] ?? ''), $query);
                    }
                    $words = collect(preg_split(
                        '/[^\p{L}\p{N}]+/u',
                        mb_strtolower(($emoji['name'] ?? '').' '.str_replace(['_', '-'], ' ', $emoji['slug'] ?? '')),
                    ))->filter()->values();

                    return $queryWords->every(fn (string $term) => $words->contains(fn (string $word) => str_starts_with($word, $term)));
                })->values();

            return response()->json([
                'categories' => $categories,
                'emojis' => $present($matches->take(160)),
                'total' => $matches->count(),
                'query' => $query,
            ]);
        }

        $slug = $data['group'] ?? $categories->first()['slug'];
        $group = $groups->firstWhere('slug', $slug);
        if (! $group) {
            throw ValidationException::withMessages(['group' => 'Choose a valid emoji group.']);
        }

        return response()->json([
            'categories' => $categories,
            'group' => $group['slug'],
            'emojis' => $present($group['emojis'] ?? []),
            'total' => count($group['emojis'] ?? []),
        ]);
    }

    private function groups()
    {
        $path = public_path('data/data-by-group.json');
        if (! is_readable($path)) {
            Log::warning('Full emoji dataset is missing; serving the built-in fallback.', ['path' => $path]);

            return collect([[
                'name' => 'Common',
                'slug' => 'common',
                'emojis' => collect(['😀', '😂', '😊', '😍', '🤔', '😎', '🥳', '😴', '👍', '👏', '🙏', '💪', '❤️', '🔥', '✨', '✅', '⭐', '📖', '✍️', '💻', '🏃', '🍽️', '🌙', '🚀'])
                    ->map(fn (string $emoji, int $index) => [
                        'emoji' => $emoji,
                        'name' => 'Common emoji '.($index + 1),
                        'slug' => 'common_'.($index + 1),
                    ])->all(),
            ]]);
        }
        $cacheKey = 'unicode-emoji-json.'.filemtime($path);

        return collect(Cache::remember($cacheKey, now()->addDay(), fn () => json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)));
    }
}
