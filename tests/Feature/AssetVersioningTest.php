<?php

namespace Tests\Feature;

use Tests\PosTestCase;

/**
 * Guards a bug that silently broke the interface in production.
 *
 * The layout stamped assets with config('app.asset_version') — a key that was
 * never defined — so every page shipped "?v=1" forever. Combined with the
 * seven-day Expires header in .htaccess, browsers kept serving the previous
 * deploy's stylesheet against the new markup, and unstyled logos rendered at
 * their natural size.
 */
class AssetVersioningTest extends PosTestCase
{
    /** @return list<string> */
    protected function viewFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_stylesheet_url_is_stamped_with_the_files_own_mtime(): void
    {
        $stamp = filemtime(public_path('assets/css/app.css'));

        $this->get('/pos/login')
            ->assertOk()
            ->assertSee("assets/css/app.css?v={$stamp}", false);
    }

    public function test_stamp_changes_when_the_file_changes(): void
    {
        $path = public_path('assets/css/app.css');
        $original = filemtime($path);

        try {
            touch($path, $original + 60);
            clearstatcache(true, $path);

            // A fresh helper cache per request is what makes this work.
            $this->get('/pos/login')
                ->assertOk()
                ->assertSee('assets/css/app.css?v='.($original + 60), false)
                ->assertDontSee("assets/css/app.css?v={$original}", false);
        } finally {
            touch($path, $original);
            clearstatcache(true, $path);
        }
    }

    public function test_no_view_references_the_undefined_asset_version_config(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            if (str_contains(file_get_contents($file), 'asset_version')) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $offenders,
            'config(app.asset_version) is undefined and always yields "1"; use asset_v() instead.');
    }

    public function test_every_css_and_js_link_goes_through_the_versioned_helper(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            $contents = file_get_contents($file);

            // Bare asset() on a stylesheet or script means no cache busting.
            if (preg_match("/asset\('assets\/(css|js)\//", $contents)) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $offenders,
            'CSS/JS must be linked with asset_v() so a deploy invalidates the browser cache.');
    }

    public function test_brand_images_declare_intrinsic_dimensions(): void
    {
        // Belt and braces: if the stylesheet is ever slow or blocked, the
        // logo must not render at its full natural size and wreck the layout.
        foreach (['/pos/login', '/admin/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            preg_match_all('/<img[^>]+aldef-[^>]*>/', $html, $matches);

            $this->assertNotEmpty($matches[0], "no brand image found on {$url}");

            foreach ($matches[0] as $tag) {
                $this->assertMatchesRegularExpression('/\swidth="\d+"/', $tag,
                    "brand image without width on {$url}: {$tag}");
                $this->assertMatchesRegularExpression('/\sheight="\d+"/', $tag,
                    "brand image without height on {$url}: {$tag}");
            }
        }
    }
}
