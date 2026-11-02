<?php

namespace Tests\Feature;

use App\Support\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_layout_shows_the_business_name_and_page_title(): void
    {
        app(Business::class)->save(['business_name' => 'Sunrise Pickle Club', 'address' => '12 Rizal St, Nabunturan']);

        $this->blade('<x-layouts.app title="Hello">Page body</x-layouts.app>')
            ->assertSee('Hello · Sunrise Pickle Club', false)
            ->assertSee('12 Rizal St, Nabunturan')
            ->assertSee('Page body');
    }

    public function test_the_messenger_link_appears_once_a_page_is_configured(): void
    {
        $this->blade('<x-layouts.app>Body</x-layouts.app>')->assertDontSee('m.me/');

        app(Business::class)->save(['messenger_page' => 'sunrisepickle']);

        $this->blade('<x-layouts.app>Body</x-layouts.app>')->assertSee('https://m.me/sunrisepickle', false);
    }

    public function test_flash_messages_are_rendered(): void
    {
        $this->withSession(['success' => 'Saved!'])
            ->blade('<x-layouts.app>Body</x-layouts.app>')
            ->assertSee('Saved!');
    }
}
