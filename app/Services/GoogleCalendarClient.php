<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarClient
{
    public const SCOPE = 'openid email https://www.googleapis.com/auth/calendar.readonly';

    public function configured(): bool
    {
        return filled(config('services.google_calendar.client_id')) && filled(config('services.google_calendar.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $this->ensureConfigured();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $this->ensureConfigured();
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful()) {
            throw new RuntimeException($this->oauthError($response->json('error'), 'Google could not complete authorization.'));
        }

        return $response->json();
    }

    public function refreshAccessToken(string $refreshToken): string
    {
        $this->ensureConfigured();
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $refreshToken,
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'grant_type' => 'refresh_token',
        ]);
        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException($this->oauthError($response->json('error'), 'Google Calendar authorization needs to be renewed.'));
        }

        return $response->json('access_token');
    }

    public function account(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo');
        if (! $response->successful() || blank($response->json('email'))) {
            throw new RuntimeException('Google did not return the linked account email.');
        }

        return $response->json();
    }

    public function events(string $accessToken, CarbonInterface $start, CarbonInterface $end, string $calendarId = 'primary'): Collection
    {
        $events = collect();
        $pageToken = null;
        do {
            $query = [
                'timeMin' => $start->toRfc3339String(),
                'timeMax' => $end->toRfc3339String(),
                'singleEvents' => 'true',
                'showDeleted' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 2500,
            ];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }
            $response = Http::withToken($accessToken)->acceptJson()->timeout(30)
                ->get('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events', $query);
            if (! $response->successful()) {
                throw new RuntimeException(match ($response->status()) {
                    401 => 'Google Calendar authorization expired. Reconnect the calendar sensor.',
                    403 => 'Google denied access to this calendar. Check the Calendar API and OAuth permissions.',
                    default => 'Google Calendar could not load events (HTTP '.$response->status().').',
                });
            }
            $events = $events->concat($response->json('items', []));
            $pageToken = $response->json('nextPageToken');
        } while (filled($pageToken));

        return $events->filter(fn ($event) => filled($event['id'] ?? null))->values();
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Google Calendar OAuth is not configured for this app.');
        }
    }

    private function oauthError(?string $error, string $fallback): string
    {
        return match ($error) {
            'invalid_grant' => 'Google Calendar authorization expired or was revoked. Reconnect the sensor.',
            'access_denied' => 'Google Calendar access was not approved.',
            default => $fallback,
        };
    }
}
