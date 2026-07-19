<?php

namespace Pencilled\Statamic\Support;

use Illuminate\Support\Arr;

/**
 * Normalises an event payload from the Pencilled API.
 *
 * The API is evolving, so this tolerates both the current shape
 * (starts_on/ends_on, no images) and enriched shapes (start_date/end_date,
 * logo_url/hero_image_url, an availability object) without breaking.
 */
class PencilledEvent
{
    /**
     * @param  array<int, array<string, mixed>>|null  $slots
     * @param  array<string, mixed>|null  $availability
     */
    public function __construct(
        public string $slug,
        public ?string $name = null,
        public ?string $location = null,
        public ?string $description = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $bookingUrl = null,
        public ?string $logoUrl = null,
        public ?string $heroImageUrl = null,
        public ?array $availability = null,
        public ?array $slots = null,
        public ?string $status = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $slug = Arr::get($data, 'slug');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $slots = Arr::get($data, 'slots');
        $slots = is_array($slots) ? array_values(array_filter($slots, 'is_array')) : null;

        return new self(
            slug: $slug,
            name: self::string($data, ['name', 'title']),
            location: self::string($data, ['location']),
            description: self::string($data, ['description']),
            startDate: self::date($data, ['start_date', 'starts_on', 'starts_at']),
            endDate: self::date($data, ['end_date', 'ends_on', 'ends_at']),
            bookingUrl: self::string($data, ['booking_url']),
            logoUrl: self::string($data, ['logo_url', 'logo', 'images.logo']),
            heroImageUrl: self::string($data, ['hero_image_url', 'hero_image', 'images.hero']),
            availability: self::availability($data, $slots),
            slots: $slots,
            status: self::string($data, ['status']),
        );
    }

    /**
     * A snapshot of availability suitable for storing on the entry. Uses the
     * API's availability object when present, otherwise derives one from any
     * slot data included in the payload.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $slots
     * @return array<string, mixed>|null
     */
    private static function availability(array $data, ?array $slots): ?array
    {
        $availability = Arr::get($data, 'availability');

        if (is_array($availability)) {
            return [
                'spots_remaining' => self::intOrNull(Arr::get($availability, 'spots_remaining')),
                'open_slots' => self::intOrNull(Arr::get($availability, 'open_slots')),
                'sold_out' => (bool) Arr::get(
                    $availability,
                    'sold_out',
                    self::intOrNull(Arr::get($availability, 'spots_remaining')) === 0
                ),
            ];
        }

        if ($slots === null) {
            return null;
        }

        $spotsRemaining = 0;
        $openSlots = 0;

        foreach ($slots as $slot) {
            $remaining = self::intOrNull(Arr::get($slot, 'spots_remaining')) ?? 0;
            $spotsRemaining += $remaining;

            if ($remaining > 0) {
                $openSlots++;
            }
        }

        return [
            'spots_remaining' => $spotsRemaining,
            'open_slots' => $openSlots,
            'sold_out' => $spotsRemaining === 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private static function string(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private static function date(array $data, array $keys): ?string
    {
        $value = self::string($data, $keys);

        if ($value === null) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
