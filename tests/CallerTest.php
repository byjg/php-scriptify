<?php

use PHPUnit\Framework\TestCase;

class CallerTest extends TestCase
{
    protected function clearTest(): void
    {
        if (file_exists('/tmp/tryme_test.txt')) {
            unlink('/tmp/tryme_test.txt');
        }
        $this->assertFalse(file_exists('/tmp/tryme_test.txt'));
    }

    #[\Override]
    public function setUp(): void
    {
        $this->clearTest();
    }

    #[\Override]
    public function tearDown(): void
    {
        $this->clearTest();
    }

    public function testCli(): void
    {
        /** @psalm-suppress ForbiddenCode */
        shell_exec( __DIR__ . '/../scripts/scriptify call /testclosure --http-get arg=10 --controller ' . __DIR__ . '/rest/app.php');

        $this->assertTrue(file_exists('/tmp/tryme_test.txt'));
        $this->assertEquals("{\"result\":\"OK\",\"arg\":\"10\"}\n", file_get_contents('/tmp/tryme_test.txt'));
    }

    public function testCliNoArgs(): void
    {
        /** @psalm-suppress ForbiddenCode */
        shell_exec( __DIR__ . '/../scripts/scriptify call /testclosure --controller ' . __DIR__ . '/rest/app.php');

        $this->assertTrue(file_exists('/tmp/tryme_test.txt'));
        $this->assertEquals("{\"result\":\"OK\",\"arg\":null}\n", file_get_contents('/tmp/tryme_test.txt'));
    }

}