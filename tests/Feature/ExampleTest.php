<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_root_redirects_to_the_cms_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('cms.dashboard'));
    }

    public function test_the_cms_dashboard_renders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('cms.dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
    }

    public function test_the_cms_dashboard_requires_cms_access(): void
    {
        $this->get(route('cms.dashboard'))
            ->assertRedirect(route('login'));

        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('cms.dashboard'))
            ->assertForbidden();
    }
}
