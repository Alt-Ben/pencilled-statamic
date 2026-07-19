<?php

namespace Pencilled\Statamic;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pencilled\Statamic\Support\PencilledEvent;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;

class EventSync
{
    public function __construct(private PencilledApi $api)
    {
    }

    /**
     * Sync the Pencilled event feed into the configured collection.
     *
     * @return array{created: int, updated: int, unchanged: int, unpublished: int}
     */
    public function run(): array
    {
        $events = $this->api->events();
        $collection = (string) config('pencilled.collection', 'events');
        $mapping = array_filter((array) config('pencilled.field_mapping', []));

        // Defensive: if the API starts including unpublished events, treat
        // them the same as events missing from the feed.
        $events = array_values(array_filter(
            $events,
            fn (PencilledEvent $event) => $event->status === null || $event->status === 'published'
        ));

        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unpublished' => 0];

        foreach ($events as $event) {
            $result[$this->syncEvent($event, $collection, $mapping)]++;
        }

        $result['unpublished'] = $this->unpublishMissing($events, $collection);

        return $result;
    }

    /**
     * @param  array<string, string>  $mapping
     * @return 'created'|'updated'|'unchanged'
     */
    private function syncEvent(PencilledEvent $event, string $collection, array $mapping): string
    {
        $entry = Entry::query()
            ->where('collection', $collection)
            ->where('slug', $event->slug)
            ->first();

        $created = false;

        if (! $entry) {
            $entry = Entry::make()
                ->collection($collection)
                ->slug($event->slug)
                ->published(true);

            $created = true;
        }

        // An event back in the feed after previously disappearing (and being
        // unpublished by the sync) should come back online.
        $republished = false;

        if (! $created && ! $entry->published() && $entry->get('pencilled') === true) {
            $entry->published(true);
            $republished = true;
        }

        $before = $entry->data()->all();

        $values = [
            'name' => $event->name,
            'location' => $event->location,
            'description' => $event->description,
            'start_date' => $event->startDate,
            'end_date' => $event->endDate,
            'booking_url' => $event->bookingUrl,
            'availability' => $event->availability,
        ];

        foreach ($values as $source => $value) {
            $target = $mapping[$source] ?? null;

            // Tolerate fields the API doesn't provide (yet) — never blank out
            // existing local data with a missing value.
            if ($target === null || $value === null) {
                continue;
            }

            $entry->set($target, $value);
        }

        if (config('pencilled.sync_images', true)) {
            $this->syncImage($entry, 'logo', $event->logoUrl, $mapping);
            $this->syncImage($entry, 'hero_image', $event->heroImageUrl, $mapping);
        }

        $entry->set('pencilled', true);
        $entry->set('pencilled_synced_at', now()->toIso8601String());

        $dirty = $created || $republished || $this->isDirty($before, $entry->data()->all());

        if ($dirty) {
            $entry->save();
        }

        return $created ? 'created' : ($dirty ? 'updated' : 'unchanged');
    }

    /**
     * Unpublish pencilled-managed entries whose slug is no longer in the
     * feed. Entries without the pencilled marker are left alone.
     *
     * @param  array<int, PencilledEvent>  $events
     */
    private function unpublishMissing(array $events, string $collection): int
    {
        $slugs = collect($events)->map(fn (PencilledEvent $event) => $event->slug)->all();

        $count = 0;

        Entry::query()
            ->where('collection', $collection)
            ->get()
            ->filter(fn ($entry) => $entry->get('pencilled') === true)
            ->reject(fn ($entry) => in_array($entry->slug(), $slugs, true))
            ->filter(fn ($entry) => $entry->published())
            ->each(function ($entry) use (&$count) {
                $entry->published(false)->save();
                $count++;
            });

        return $count;
    }

    /**
     * Download a remote image into the asset container and assign it to the
     * mapped field. Re-downloads only when the source URL changes.
     *
     * @param  array<string, string>  $mapping
     */
    private function syncImage(mixed $entry, string $field, ?string $url, array $mapping): void
    {
        $target = $mapping[$field] ?? null;

        if ($target === null || $url === null) {
            return;
        }

        $container = AssetContainer::find((string) config('pencilled.asset_container', 'assets'));

        if (! $container) {
            Log::warning('Pencilled: asset container not found, skipping image sync.', [
                'container' => config('pencilled.asset_container'),
            ]);

            return;
        }

        $sources = (array) $entry->get('pencilled_media', []);
        $path = 'pencilled/'.$entry->slug().'-'.str_replace('_', '-', $field).'.'.$this->extension($url);

        if (($sources[$field] ?? null) === $url && $container->disk()->exists($path)) {
            $entry->set($target, $path);

            return;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if ($response->failed()) {
                Log::warning('Pencilled: failed to download image.', ['url' => $url, 'status' => $response->status()]);

                return;
            }

            $container->disk()->put($path, $response->body());
            $container->makeAsset($path)->save();

            $sources[$field] = $url;

            $entry->set($target, $path);
            $entry->set('pencilled_media', $sources);
        } catch (\Throwable $e) {
            Log::warning('Pencilled: image sync failed.', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    private function extension(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'], true)
            ? $extension
            : 'jpg';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function isDirty(array $before, array $after): bool
    {
        ksort($before);
        ksort($after);

        // The sync timestamp always changes, so ignore it when deciding
        // whether anything meaningful was updated.
        unset($before['pencilled_synced_at'], $after['pencilled_synced_at']);

        return $before != $after;
    }
}
