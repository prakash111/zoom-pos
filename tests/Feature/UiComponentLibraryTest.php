<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiComponentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_button_renders(): void
    {
        $html = (string) view('components.ui.button', ['slot' => 'Save', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('bg-theme-primary', $html);
    }

    public function test_ui_card_renders(): void
    {
        $html = (string) view('components.ui.card', ['slot' => 'Body', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('rounded-3xl', $html);
    }

    public function test_ui_badge_renders(): void
    {
        $html = (string) view('components.ui.badge', ['slot' => 'Active', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('Active', $html);
    }

    public function test_ui_input_renders(): void
    {
        $html = (string) view('components.ui.input', ['label' => 'Name', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('Name', $html);
    }

    public function test_ui_select_renders(): void
    {
        $html = (string) view('components.ui.select', ['label' => 'Category', 'slot' => '<option>A</option>', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('Category', $html);
    }

    public function test_ui_icon_renders_known_and_unknown_names(): void
    {
        $html = (string) view('components.ui.icon', ['name' => 'home', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('<svg', $html);

        // Unknown icon name must not throw — it renders an empty path, not a crash.
        $html = (string) view('components.ui.icon', ['name' => 'does-not-exist', 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('<svg', $html);
    }
}
