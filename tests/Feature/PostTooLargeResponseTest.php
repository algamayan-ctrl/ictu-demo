<?php

namespace Tests\Feature;

use Tests\TestCase;

class PostTooLargeResponseTest extends TestCase
{
    public function test_oversized_browser_submission_shows_guidance_instead_of_a_debug_trace(): void
    {
        config(['app.debug' => true]);

        $this->call('POST', '/requests', [], [], [], [
            'CONTENT_LENGTH' => (string) PHP_INT_MAX,
            'HTTP_ACCEPT' => 'text/html',
        ])->assertStatus(413)
            ->assertSeeText('Upload too large')
            ->assertSeeText('No submission was saved.')
            ->assertSeeText('Back button')
            ->assertDontSee('PostTooLargeException')
            ->assertDontSee('Stack Trace');
    }

    public function test_oversized_json_submission_returns_a_safe_json_error(): void
    {
        config(['app.debug' => true]);

        $this->call('POST', '/requests', [], [], [], [
            'CONTENT_LENGTH' => (string) PHP_INT_MAX,
            'HTTP_ACCEPT' => 'application/json',
        ])->assertStatus(413)
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonPath('message', 'The combined upload exceeds the server limit. Please choose smaller files and try again. No submission was saved.');
    }
}
