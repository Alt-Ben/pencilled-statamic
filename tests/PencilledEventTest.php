<?php

namespace Pencilled\Statamic\Tests;

use PHPUnit\Framework\TestCase;
use Pencilled\Statamic\Support\PencilledEvent;

class PencilledEventTest extends TestCase
{
    public function test_it_parses_the_current_api_shape(): void
    {
        $event = PencilledEvent::fromArray([
            'name' => 'The Surge',
            'slug' => 'the-surge',
            'location' => 'Burton Reservoir',
            'description' => 'Post-swim heat therapy.',
            'starts_on' => '2026-10-17',
            'ends_on' => null,
            'booking_url' => 'https://pencilled.io/the-recovery-dock/the-surge',
        ]);

        $this->assertNotNull($event);
        $this->assertSame('The Surge', $event->name);
        $this->assertSame('the-surge', $event->slug);
        $this->assertSame('2026-10-17', $event->startDate);
        $this->assertNull($event->endDate);
        $this->assertNull($event->logoUrl);
        $this->assertNull($event->availability);
    }

    public function test_it_parses_an_enriched_api_shape(): void
    {
        $event = PencilledEvent::fromArray([
            'name' => 'The Surge',
            'slug' => 'the-surge',
            'start_date' => '2026-10-17',
            'end_date' => '2026-10-18',
            'logo_url' => 'https://pencilled.io/storage/logo.png',
            'hero_image_url' => 'https://pencilled.io/storage/hero.jpg',
            'availability' => [
                'spots_remaining' => 12,
                'open_slots' => 3,
            ],
        ]);

        $this->assertNotNull($event);
        $this->assertSame('2026-10-17', $event->startDate);
        $this->assertSame('2026-10-18', $event->endDate);
        $this->assertSame('https://pencilled.io/storage/logo.png', $event->logoUrl);
        $this->assertSame('https://pencilled.io/storage/hero.jpg', $event->heroImageUrl);
        $this->assertSame(12, $event->availability['spots_remaining']);
        $this->assertSame(3, $event->availability['open_slots']);
        $this->assertFalse($event->availability['sold_out']);
    }

    public function test_it_derives_availability_from_slots(): void
    {
        $event = PencilledEvent::fromArray([
            'slug' => 'the-surge',
            'starts_on' => '2026-10-17',
            'slots' => [
                ['spots_remaining' => 2, 'price_formatted' => '£15'],
                ['spots_remaining' => 0],
                ['spots_remaining' => 6],
            ],
        ]);

        $this->assertNotNull($event);
        $this->assertSame(8, $event->availability['spots_remaining']);
        $this->assertSame(2, $event->availability['open_slots']);
        $this->assertFalse($event->availability['sold_out']);
    }

    public function test_it_rejects_payloads_without_a_slug(): void
    {
        $this->assertNull(PencilledEvent::fromArray(['name' => 'No slug']));
    }
}
