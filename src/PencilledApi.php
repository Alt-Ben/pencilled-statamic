<?php

namespace Pencilled\Statamic;

use Illuminate\Support\Facades\Http;
use Pencilled\Statamic\Support\PencilledEvent;
use RuntimeException;

class PencilledApi
{
    /**
     * Fetch the full event feed for the configured operator.
     *
     * @return array<int, PencilledEvent>
     */
    public function events(): array
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->get($this->url('events'));

        if ($response->failed()) {
            throw new RuntimeException(
                "Pencilled API request failed ({$response->status()}) for {$this->url('events')}"
            );
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Pencilled API returned an unexpected payload (missing data array).');
        }

        return collect($data)
            ->filter(fn ($event) => is_array($event))
            ->map(fn (array $event) => PencilledEvent::fromArray($event))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Fetch a single event, including its slots, or null if unavailable.
     */
    public function event(string $slug): ?PencilledEvent
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->get($this->url('events/'.$slug));

        if ($response->failed()) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? PencilledEvent::fromArray($data) : null;
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('pencilled.base_url'), '/');
        $operator = trim((string) config('pencilled.operator'), '/');

        if ($base === '' || $operator === '') {
            throw new RuntimeException(
                'Pencilled is not configured. Set PENCILLED_BASE_URL and PENCILLED_OPERATOR in your .env file.'
            );
        }

        return "{$base}/api/v1/operators/{$operator}/{$path}";
    }
}
