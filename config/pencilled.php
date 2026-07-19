<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pencilled API
    |--------------------------------------------------------------------------
    |
    | The base URL of the Pencilled instance you book through, and the slug
    | of your operator account. Events are read from:
    | {base_url}/api/v1/operators/{operator}/events
    |
    */

    'base_url' => env('PENCILLED_BASE_URL', 'https://pencilled.io'),

    'operator' => env('PENCILLED_OPERATOR'),

    /*
    |--------------------------------------------------------------------------
    | Collection
    |--------------------------------------------------------------------------
    |
    | The handle of the collection that synced events are written to. Entries
    | are matched by slug, and any entry managed by Pencilled is marked with
    | a `pencilled: true` field. Entries without that marker are never
    | unpublished by the sync.
    |
    */

    'collection' => env('PENCILLED_COLLECTION', 'events'),

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | When sync_images is enabled, event logos and hero images published by
    | the Pencilled API are downloaded into this asset container (under a
    | `pencilled` folder) and assigned to the mapped entry fields.
    |
    */

    'asset_container' => env('PENCILLED_ASSET_CONTAINER', 'assets'),

    'sync_images' => true,

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Pencilled can notify your site when events change by POSTing to
    | /pencilled/webhook. Requests are verified against this shared secret
    | using an HMAC-SHA256 signature in the X-Pencilled-Signature header.
    | Leave the secret empty to disable the webhook entirely.
    |
    */

    'webhook_secret' => env('PENCILLED_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Scheduled Sync
    |--------------------------------------------------------------------------
    |
    | When enabled, `php please pencilled:sync` runs every ten minutes via
    | the Laravel scheduler as a safety net alongside the webhook.
    |
    */

    'schedule' => true,

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Maps Pencilled event fields to the fields on your entry blueprint, so
    | the addon can slot into an existing collection. Keys are Pencilled
    | fields, values are your blueprint handles. Remove a line (or set the
    | value to null) to stop that field from being synced.
    |
    */

    'field_mapping' => [
        'name' => 'title',
        'location' => 'location',
        'description' => 'description',
        'start_date' => 'start_date',
        'end_date' => 'end_date',
        'booking_url' => 'booking_url',
        'availability' => 'availability',
        'logo' => 'logo',
        'hero_image' => 'hero_image',
    ],

];
