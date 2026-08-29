<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_cms_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('cms.dashboard'));
    }

    public function test_the_cms_dashboard_renders(): void
    {
        $response = $this->get(route('cms.dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
    }
}
