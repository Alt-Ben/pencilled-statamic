<?php

namespace Pencilled\Statamic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pencilled\Statamic\Jobs\SyncEvents;

class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('pencilled.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook not configured.'], 403);
        }

        $signature = (string) $request->header('X-Pencilled-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if (! is_string($event) || ! str_starts_with($event, 'events.')) {
            return response()->json(['message' => 'Ignored.'], 202);
        }

        // Queued when a queue driver is configured; the sync driver simply
        // runs it inline within this request.
        SyncEvents::dispatch();

        return response()->json(['message' => 'Sync dispatched.']);
    }
}
