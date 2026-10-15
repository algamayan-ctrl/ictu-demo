<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google's OAuth consent screen links to these pages, so they must load for
 * anyone, signed in or not, and be reachable from the public home page.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_is_public_and_describes_google_data_use(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Data Privacy Act of 2012')
            ->assertSee('Google API Services User Data Policy')
            ->assertSee(route('legal.terms'), false);
    }

    public function test_terms_of_service_is_public(): void
    {
        $this->get('/terms-of-service')
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee(route('legal.privacy'), false);
    }

    public function test_contact_mailbox_comes_from_configuration(): void
    {
        config(['app.privacy_contact' => 'privacy.desk@example.test']);

        $this->get('/privacy-policy')->assertSee('mailto:privacy.desk@example.test', false);
        $this->get('/terms-of-service')->assertSee('mailto:privacy.desk@example.test', false);
    }

    public function test_pages_render_without_a_contact_mailbox(): void
    {
        config(['app.privacy_contact' => null]);

        $this->get('/privacy-policy')->assertOk()->assertDontSee('mailto:', false);
        $this->get('/terms-of-service')->assertOk()->assertDontSee('mailto:', false);
    }

    public function test_home_page_links_to_both_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.terms'), false);
    }

    public function test_signed_in_users_can_open_both_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('access_classification', AccessClassification::SpmuHead->value)->firstOrFail();

        $this->actingAs($user)->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
        $this->actingAs($user)->get('/terms-of-service')->assertOk()->assertSee('Terms of Service');
    }
}
