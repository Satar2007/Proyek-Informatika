<?php

namespace Tests\Feature;

use Tests\TestCase;

class GlobalUiPolishTest extends TestCase
{
    public function test_global_polish_stylesheet_is_loaded_after_existing_theme_styles(): void
    {
        $layoutPath = resource_path('views/layouts/app.blade.php');
        $cssPath = public_path('css/global-ui-polish.css');

        $this->assertFileExists($cssPath);

        $layout = file_get_contents($layoutPath);
        $this->assertIsString($layout);

        $menuPhotos = "css/menu-photos.css') }}?v=2";
        $globalPolish = "css/global-ui-polish.css') }}?v=1";

        $this->assertStringContainsString($menuPhotos, $layout);
        $this->assertStringContainsString($globalPolish, $layout);
        $this->assertLessThan(
            strpos($layout, $globalPolish),
            strpos($layout, $menuPhotos),
            'Global polish must load after existing page/theme styles.'
        );
    }

    public function test_global_polish_contains_overflow_focus_mobile_and_reduced_motion_guards(): void
    {
        $css = file_get_contents(public_path('css/global-ui-polish.css'));
        $this->assertIsString($css);

        foreach ([
            'body{overflow-x:hidden}',
            ':focus-visible',
            '@media(max-width:767px)',
            '@media(max-width:520px)',
            '@media(prefers-reduced-motion:reduce)',
            'overscroll-behavior-inline:contain',
        ] as $requiredRule) {
            $this->assertStringContainsString($requiredRule, $css);
        }
    }
}
