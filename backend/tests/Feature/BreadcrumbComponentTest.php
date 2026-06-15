<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BreadcrumbComponentTest extends TestCase
{
    public function test_renders_trail_with_links_for_intermediate_and_span_for_last(): void
    {
        $html = $this->render([
            ['label' => 'Settings', 'url' => 'https://app.test/settings'],
            ['label' => 'Users'],
        ]);

        $this->assertStringContainsString('<nav aria-label="Breadcrumb">', $html);
        $this->assertStringContainsString('href="https://app.test/settings"', $html);
        $this->assertStringContainsString('>Settings</a>', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('>Users</span>', $html);
        $this->assertStringNotContainsString('href="https://app.test/users"', $html);
    }

    public function test_no_auto_prepend_regardless_of_trail_size(): void
    {
        // Component renders exactly the items the caller passed — no
        // Dashboard auto-prepend at any size. Dashboard is sidebar-reachable;
        // a "Dashboard /" prefix on every page is noise.
        $homeUrl = route('dashboard');

        foreach ([1, 2, 3, 4] as $size) {
            $items = [];
            for ($i = 0; $i < $size - 1; $i++) {
                $items[] = ['label' => "Mid {$i}", 'url' => 'https://app.test/x'];
            }
            $items[] = ['label' => 'Leaf'];

            $html = $this->render($items);

            $this->assertSame(0, substr_count($html, 'href="'.$homeUrl.'"'),
                "Home link should be absent on {$size}-item trail");
        }
    }

    public function test_caller_can_explicitly_include_home(): void
    {
        // If the caller wants Home as the first crumb, they include it
        // themselves. The component renders it once, like any other item.
        $homeUrl = route('dashboard');

        $html = $this->render([
            ['label' => 'Dashboard', 'url' => $homeUrl],
            ['label' => 'Sub Page'],
        ]);

        $this->assertSame(1, substr_count($html, 'href="'.$homeUrl.'"'));
    }

    public function test_empty_items_renders_no_crumbs(): void
    {
        // Caller passed no items → trail stays empty. An empty <ol> is fine;
        // nothing crashes, no Dashboard label is forced into existence.
        $html = $this->render([]);

        $this->assertStringContainsString('<nav aria-label="Breadcrumb">', $html);
        $this->assertStringNotContainsString('aria-current="page"', $html);
    }

    private function render(array $items): string
    {
        return Blade::render('<x-breadcrumb :items="$items" />', ['items' => $items]);
    }
}
