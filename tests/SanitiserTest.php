<?php

namespace Pencilled\Statamic\Tests;

use PHPUnit\Framework\TestCase;
use Pencilled\Statamic\Support\Sanitiser;

class SanitiserTest extends TestCase
{
    public function test_it_keeps_the_allowlist(): void
    {
        $this->assertSame(
            '<p>Post-swim <strong>heat</strong> therapy.</p><ul><li>Towels</li></ul>',
            Sanitiser::clean('<p>Post-swim <strong>heat</strong> therapy.</p><ul><li>Towels</li></ul>')
        );
    }

    public function test_it_removes_scripts_event_handlers_and_unsafe_links(): void
    {
        $this->assertSame(
            '<p>Hi</p>',
            Sanitiser::clean('<p onclick="x()">Hi<script>alert(1)</script></p>')
        );

        $this->assertSame(
            'bad</a> <a href="https://example.com" rel="nofollow noopener">ok</a>',
            Sanitiser::clean('<a href="javascript:alert(1)">bad</a> <a href="https://example.com" onmouseover="x()">ok</a>')
        );
    }

    public function test_it_leaves_plain_text_alone(): void
    {
        $this->assertSame('Post-swim heat therapy.', Sanitiser::clean('Post-swim heat therapy.'));
        $this->assertNull(Sanitiser::clean('<script>alert(1)</script>'));
        $this->assertNull(Sanitiser::clean('  '));
        $this->assertNull(Sanitiser::clean(null));
    }
}
