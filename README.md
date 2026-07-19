# Pencilled for Statamic

Sync events and live availability from the [Pencilled](https://pencilled.io) booking platform into your Statamic site.

Your events live in a normal Statamic collection — so your templates, blueprints and editors work exactly as they always have — while Pencilled keeps the entries up to date and provides live availability at render time.

## Features

- **Event sync** — `php please pencilled:sync` upserts events from your Pencilled operator feed into a collection, matched by slug. Titles, dates, locations, descriptions, booking URLs and availability snapshots are kept in sync, and event imagery is downloaded into an asset container.
- **Webhook receiver** — Pencilled notifies your site the moment events change. Requests are verified with an HMAC-SHA256 signature and the sync is queued (or run inline when no queue is configured).
- **Scheduled sync** — a ten-minute scheduled sync runs as a safety net alongside the webhook.
- **Antlers tags** — `{{ pencilled:availability }}` for live spots remaining on an event, and `{{ pencilled:events }}` to render the feed straight from the API.
- **Blueprint mapping** — map Pencilled fields onto your existing blueprint handles, so the addon fits into a site you have already built.

## Requirements

- PHP 8.2+
- Statamic 5 or 6

## Installation

```bash
composer require pencilled/statamic
```

Publish the config:

```bash
php artisan vendor:publish --tag=pencilled-config
```

Add your credentials to `.env`:

```dotenv
PENCILLED_BASE_URL=https://pencilled.io
PENCILLED_OPERATOR=your-operator-slug
PENCILLED_WEBHOOK_SECRET=your-shared-secret
```

Then run your first sync:

```bash
php please pencilled:sync
```

## Configuration

`config/pencilled.php`:

| Key | Default | Description |
| --- | --- | --- |
| `base_url` | `https://pencilled.io` | The Pencilled instance to read from. |
| `operator` | — | Your operator slug on Pencilled. |
| `collection` | `events` | The collection synced entries are written to. |
| `asset_container` | `assets` | Where event imagery is downloaded (into a `pencilled` folder). |
| `sync_images` | `true` | Download logo and hero images published by the API. |
| `webhook_secret` | — | Shared secret for webhook signature verification. |
| `schedule` | `true` | Run the sync every ten minutes via the scheduler. |
| `field_mapping` | see below | Pencilled field → blueprint handle mapping. |

### Field mapping

If your blueprint uses different handles, remap them:

```php
'field_mapping' => [
    'name' => 'title',
    'location' => 'venue',
    'description' => 'summary',
    'start_date' => 'start_date',
    'end_date' => 'end_date',
    'booking_url' => 'booking_url',
    'availability' => 'availability',
    'logo' => 'logo',
    'hero_image' => 'hero_image',
],
```

Remove a line (or set it to `null`) to stop that field syncing. Fields the API doesn't provide are simply skipped — existing entry data is never blanked out.

### How syncing behaves

- Entries are matched by slug. An existing entry with a matching slug is adopted and updated in place, so you can install the addon into a site with hand-created events.
- Every synced entry is marked with `pencilled: true`. Entries without that marker are never modified or unpublished by the sync.
- When a slug disappears from the feed, the matching pencilled-marked entry is unpublished (not deleted).
- Images are only re-downloaded when their source URL changes.

## Webhook

Point Pencilled at:

```
POST https://your-site.com/pencilled/webhook
```

The request body looks like `{"event": "events.updated", "operator": "...", "timestamp": "..."}` and must be signed with a hex HMAC-SHA256 of the raw body (using your shared secret) in the `X-Pencilled-Signature` header. Invalid signatures receive a 403.

When a queue driver is configured the sync runs as a queued job; otherwise it runs inline.

## Scheduling

The addon registers `pencilled:sync` to run every ten minutes. Make sure your server runs the Laravel scheduler:

```
* * * * * php /path/to/site/artisan schedule:run >> /dev/null 2>&1
```

Set `'schedule' => false` in the config to disable it.

## Tags

### Live availability

```antlers
{{ pencilled:availability event="the-surge" }}
    {{ if sold_out }}
        Sold out
    {{ else }}
        {{ spots_remaining }} spots left across {{ open_slots }} sessions
        {{ if price_formatted }}— from {{ price_formatted }}{{ /if }}
    {{ /if }}
{{ /pencilled:availability }}
```

Responses are cached for 60 seconds. If the API is unreachable the tag returns nothing, so wrap conditional output accordingly.

### Rendering the feed directly

```antlers
{{ pencilled:events }}
    <h2>{{ title }}</h2>
    <p>{{ location }} — {{ start_date }}</p>
    <a href="{{ booking_url }}">Book now</a>
{{ /pencilled:events }}
```

## Testing

```bash
composer install
./vendor/bin/phpunit
```

## Licence

MIT — see [LICENSE](LICENSE).
