<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Outside debug mode, stack traces carry no argument values.
 *
 * A logged trace otherwise quotes the first characters of every string
 * passed down the call stack, which can be document bytes or personal
 * details. Debug mode keeps full traces for development.
 */
class ExceptionArgumentRedactionTest extends TestCase
{
    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = (string) ini_get('zend.exception_ignore_args');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', $this->original);

        parent::tearDown();
    }

    public function test_arguments_are_dropped_from_traces_when_debug_is_off(): void
    {
        ini_set('zend.exception_ignore_args', '0');
        config(['app.debug' => false]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('1', ini_get('zend.exception_ignore_args'));
        $this->assertStringNotContainsString('CONFIDENTIAL', (string) $this->failWith('CONFIDENTIAL-VALUE'));
    }

    public function test_debug_mode_keeps_full_traces_for_development(): void
    {
        ini_set('zend.exception_ignore_args', '0');
        config(['app.debug' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('0', ini_get('zend.exception_ignore_args'));
        $this->assertStringContainsString('CONFIDENTIAL', (string) $this->failWith('CONFIDENTIAL-VALUE'));
    }

    public function test_debug_is_off_unless_an_environment_turns_it_on(): void
    {
        /* config/app.php: an environment that sets nothing is not in debug mode. */
        $this->assertStringContainsString("env('APP_DEBUG', false)", (string) file_get_contents(base_path('config/app.php')));
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false\r?$/m', (string) file_get_contents(base_path('.env.docker.example')));
    }

    private function failWith(string $value): RuntimeException
    {
        return new RuntimeException('failure carrying an argument: '.strlen($value));
    }
}
