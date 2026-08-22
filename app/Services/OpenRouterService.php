<?php

namespace App\Services;

use App\Models\ApiCall;
use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenRouterService
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    public function models(User $user, bool $images = false): array
    {
        return $this->request($user, $images ? 'image_models' : 'models', null, null, null,
            fn ($http) => $http->get(self::BASE_URL.($images ? '/images/models' : '/models'))
        )['data'] ?? [];
    }

    public function chat(User $user, DailyLog $log, LogBlock $block, string $model, array $messages, array $options = [], string $operation = 'chat'): array
    {
        return $this->request($user, $operation, $log, $block, $model,
            fn ($http) => $http->timeout(120)->post(self::BASE_URL.'/chat/completions', array_merge([
                'model' => $model,
                'messages' => $messages,
                'max_completion_tokens' => 1200,
            ], $options))
        );
    }

    public function complete(User $user, string $model, array $messages, array $options = [], string $operation = 'completion'): array
    {
        return $this->request($user, $operation, null, null, $model,
            fn ($http) => $http->timeout(120)->post(self::BASE_URL.'/chat/completions', array_merge([
                'model' => $model,
                'messages' => $messages,
                'max_completion_tokens' => 2000,
            ], $options))
        );
    }

    public function image(User $user, DailyLog $log, LogBlock $block, string $model, string $prompt): array
    {
        return $this->request($user, 'image', $log, $block, $model,
            fn ($http) => $http->timeout(180)->post(self::BASE_URL.'/images', [
                'model' => $model,
                'prompt' => $prompt,
            ])
        );
    }

    public function transcribe(User $user, DailyLog $log, LogBlock $block, string $model, string $data, string $format): array
    {
        return $this->request($user, 'transcription', $log, $block, $model,
            fn ($http) => $http->timeout(180)->post(self::BASE_URL.'/audio/transcriptions', [
                'model' => $model,
                'input_audio' => ['data' => $data, 'format' => $format],
            ])
        );
    }

    private function request(User $user, string $operation, ?DailyLog $log, ?LogBlock $block, ?string $model, callable $callback): array
    {
        $apiKey = $user->openRouterApiKey();
        if (! $apiKey) {
            $message = $user->hasInvalidOpenRouterApiKey()
                ? 'Your saved OpenRouter API key can no longer be decrypted. Replace it in Settings.'
                : 'Add your OpenRouter API key in Settings first.';

            throw ValidationException::withMessages(['api_key' => $message]);
        }

        $started = microtime(true);
        $response = null;
        $error = null;

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name', "Captain's Log"),
                ])
                ->withOptions(['http_errors' => false]);
            /** @var Response $result */
            $result = $callback($response);
            $body = $result->json() ?: [];
            $error = $result->successful() ? null : data_get($body, 'error.message', 'OpenRouter request failed.');
        } catch (\Throwable $e) {
            $result = null;
            $body = [];
            $error = $e->getMessage();
        }

        $usage = $body['usage'] ?? [];
        ApiCall::create([
            'user_id' => $user->id,
            'daily_log_id' => $log?->id,
            'log_block_id' => $block?->id,
            'operation' => $operation,
            'model' => $model,
            'request_id' => $body['id'] ?? ($result?->header('x-request-id')),
            'status_code' => $result?->status(),
            'prompt_tokens' => $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'cost' => $usage['cost'] ?? 0,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'error' => $error,
        ]);

        if ($error) {
            throw ValidationException::withMessages(['openrouter' => $error]);
        }

        return $body;
    }
}
