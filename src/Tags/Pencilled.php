<?php

namespace Pencilled\Statamic\Tags;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pencilled\Statamic\PencilledApi;
use Pencilled\Statamic\Support\PencilledEvent;
use Statamic\Tags\Tags;

class Pencilled extends Tags
{
    protected static $handle = 'pencilled';

    /**
     * {{ pencilled:availability event="the-surge" }}
     *
     * Live availability for a single event, cached for 60 seconds.
     * Exposes spots_remaining, open_slots, sold_out and price_formatted.
     *
     * When the API is unreachable (offline dev, static site builds) it falls
     * back to the availability last synced onto the entry, so the tag always
     * renders a value that a rebuild will refresh.
     *
     * @return array<string, mixed>|null
     */
    public function availability(): ?array
    {
        $slug = (string) $this->params->get('event', $this->params->get('slug'));

        if ($slug === '') {
            return null;
        }

        return $this->live($slug) ?? $this->synced();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function live(string $slug): ?array
    {
        return $this->remember('availability.'.$slug, function () use ($slug) {
            $event = app(PencilledApi::class)->event($slug);

            if (! $event) {
                return null;
            }

            $firstSlot = $event->slots[0] ?? null;

            return ($event->availability ?? [
                'spots_remaining' => null,
                'open_slots' => null,
                'sold_out' => false,
            ]) + [
                'price_formatted' => $firstSlot['price_formatted'] ?? null,
                'currency' => $firstSlot['currency'] ?? null,
            ];
        });
    }

    /**
     * The availability snapshot the sync last wrote onto the current entry.
     *
     * @return array<string, mixed>|null
     */
    private function synced(): ?array
    {
        $availability = $this->context->value('availability');

        if ($availability instanceof \Statamic\Fields\Value) {
            $availability = $availability->value();
        }

        if (! is_array($availability)) {
            return null;
        }

        return $availability + [
            'price_formatted' => null,
            'currency' => null,
        ];
    }

    /**
     * {{ pencilled:events }} ... {{ /pencilled:events }}
     *
     * The live event feed straight from the API, for sites that render
     * events without syncing them into a collection. Cached for 60 seconds.
     *
     * @return array<int, array<string, mixed>>
     */
    public function events(): array
    {
        return $this->remember('events', function () {
            return collect(app(PencilledApi::class)->events())
                ->map(fn (PencilledEvent $event) => [
                    'title' => $event->name,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'location' => $event->location,
                    'description' => $event->description,
                    'start_date' => $event->startDate,
                    'end_date' => $event->endDate,
                    'booking_url' => $event->bookingUrl,
                    'logo_url' => $event->logoUrl,
                    'hero_image_url' => $event->heroImageUrl,
                    'availability' => $event->availability,
                ])
                ->all();
        }) ?? [];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function remember(string $key, callable $callback)
    {
        try {
            return Cache::remember('pencilled.'.$key, 60, $callback);
        } catch (\Throwable $e) {
            Log::warning('Pencilled: tag lookup failed.', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
