<?php

namespace Pencilled\Statamic\Tests;

use Illuminate\Support\Facades\Bus;
use Pencilled\Statamic\Jobs\SyncEvents;
use Pencilled\Statamic\ServiceProvider;
use Statamic\Testing\AddonTestCase;

class WebhookTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('pencilled.webhook_secret', 'testing-secret');
    }

    public function test_a_valid_signature_dispatches_a_sync(): void
    {
        Bus::fake();

        $body = json_encode([
            'event' => 'events.updated',
            'operator' => 'the-recovery-dock',
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->call('POST', '/pencilled/webhook', [], [], [], [
            'HTTP_X-Pencilled-Signature' => hash_hmac('sha256', $body, 'testing-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        Bus::assertDispatched(SyncEvents::class);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        Bus::fake();

        $body = json_encode(['event' => 'events.updated']);

        $this->call('POST', '/pencilled/webhook', [], [], [], [
            'HTTP_X-Pencilled-Signature' => 'nonsense',
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();

        Bus::assertNotDispatched(SyncEvents::class);
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        Bus::fake();

        $this->postJson('/pencilled/webhook', ['event' => 'events.updated'])->assertForbidden();

        Bus::assertNotDispatched(SyncEvents::class);
    }

    public function test_unrelated_events_are_acknowledged_but_ignored(): void
    {
        Bus::fake();

        $body = json_encode(['event' => 'operators.updated']);

        $this->call('POST', '/pencilled/webhook', [], [], [], [
            'HTTP_X-Pencilled-Signature' => hash_hmac('sha256', $body, 'testing-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(202);

        Bus::assertNotDispatched(SyncEvents::class);
    }
}
