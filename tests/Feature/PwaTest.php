<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_manifest_is_valid_and_icons_exist(): void
    {
        $m = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('HaloDek', $m['name']);
        $this->assertSame('standalone', $m['display']);
        $purposes = collect($m['icons'])->pluck('purpose')->unique()->sort()->values()->all();
        $this->assertSame(['any', 'maskable'], $purposes);
        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path($icon['src']));
        }
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_pages_link_manifest_and_service_worker_never_caches_html(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('rel="manifest"', false)->assertSee('name="halodek-sw"', false)->assertSee('theme-color', false);

        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("req.mode === 'navigate'", $sw);
        // halaman (navigate) hanya fetch + fallback offline; cache.put hanya di cabang berkas statis
        $this->assertSame(1, substr_count($sw, 'cache.put('));
        $this->assertStringContainsString('STATIC_RE', $sw);
    }
}
