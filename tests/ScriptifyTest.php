<?php

use ByJG\Scriptify\Scriptify;
use \PHPUnit\Framework\TestCase;

class ScriptifyTest extends TestCase
{
    protected $serviceWriter = null;

    protected function clearTest(): void
    {
        $fileList = [
            '/tmp/test.service',
            '/tmp/test.env',
        ];

        foreach ($fileList as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
            $this->assertFalse(file_exists($file));
        }

        Scriptify::setWriter(null);
    }

    #[\Override]
    public function setUp(): void
    {
        $this->clearTest();
        $this->serviceWriter = new \ByJG\Scriptify\ServiceWriter('/tmp');
    }

    #[\Override]
    public function tearDown(): void
    {
        $this->clearTest();
    }

    protected function read($file): string
    {
        $contents = file_get_contents($file);

        $cwd = getcwd();
        $contents = str_replace(
            [
                PHP_BINARY,
                $cwd !== false ? $cwd : '',
            ],
            [
                'PHP_BINARY',
                'CURDIR',
            ],
            $contents
        );

        return $contents;
    }

    public function testInstallMock(): void
    {
        Scriptify::setWriter($this->serviceWriter);
        $result = Scriptify::install('test', 'ByJG\Scriptify\Sample\TryMe::ping', 'vendor/autoload.php', __DIR__ . '/../', "systemd", 'Custom Description', [], []);
        $this->assertTrue($result);
        $this->assertEquals($this->read(__DIR__ . '/expected/test.env'), $this->read('/tmp/test.env'));
        $this->assertEquals($this->read(__DIR__ . '/expected/test.service'), $this->read('/tmp/test.service'));
    }

    public function testInstallMockWithEnv(): void
    {
        Scriptify::setWriter($this->serviceWriter);
        $result = Scriptify::install('test', 'ByJG\Scriptify\Sample\TryMe::ping', 'vendor/autoload.php', __DIR__ . '/../', "systemd", 'Custom Description', ["a" => "1", "b" => 2], ['APP_ENV' => 'test', 'TEST' => 'true']);
        $this->assertTrue($result);
        $this->assertEquals($this->read(__DIR__ . '/expected/test-with-env.env'), $this->read('/tmp/test.env'));
        $this->assertEquals($this->read(__DIR__ . '/expected/test-with-env.service'), $this->read('/tmp/test.service'));
    }

    /**
     * This test will fail if you don't have root permission
     *
     * @return void
     */
    public function testCommandLine()
    {
        if (posix_getuid() !== 0) {
            $this->markTestSkipped('This test will fail if you don\'t have root permission');
        }

        /** @psalm-suppress ForbiddenCode */
        $psResult = shell_exec("ps -p 1 -o comm=");
        if (empty($psResult) || trim($psResult) !== "systemd") {
            $this->markTestSkipped('This test will fail if you don\'t have systemd');
        };

        $command = __DIR__ . "/../scripts/scriptify install " .
            "--template systemd " .
            "--description 'Custom Description' " .
            "--class 'ByJG\Scriptify\Sample\TryMe::ping' " .
            "--bootstrap 'vendor/autoload.php' " .
            "--rootdir '" . __DIR__ . "/../' " .
            "--env 'APP_ENV=test' " .
            "--env 'TEST=true' " .
            "--args '1' " .
            "--args '2' " .
            "test";

        /** @psalm-suppress ForbiddenCode */
        shell_exec($command);

        $this->assertEquals($this->read(__DIR__ . '/expected/test-with-env.env'), $this->read('/etc/scriptify/test.env'));
        $this->assertEquals($this->read(__DIR__ . '/expected/test-with-env.service'), $this->read('/etc/systemd/system/test.service'));

        $services = Scriptify::listServices();
        $this->assertEquals(["test"], $services);

        Scriptify::uninstall('test');

        $services = Scriptify::listServices();
        $this->assertEquals([], $services);
    }


    /**
     * This test will fail if you don't have root permission
     *
     * @return void
     */
    public function testInstall()
    {
        if (posix_getuid() !== 0) {
            $this->markTestSkipped('This test will fail if you don\'t have root permission');
        }

        /** @psalm-suppress ForbiddenCode */
        $psResult = shell_exec("ps -p 1 -o comm=");
        if (empty($psResult) || trim($psResult) !== "systemd") {
            $this->markTestSkipped('This test will fail if you don\'t have systemd');
        };

        Scriptify::install('test', 'ByJG\Scriptify\Sample\TryMe::ping', 'vendor/autoload.php', __DIR__ . '/../', "systemd", 'Custom Description', ["1", 2], ['APP_ENV' => 'test', 'TEST' => 'true']);

        $this->assertEquals($this->read(__DIR__ . '/expected/test-with-env.env'), $this->read('/etc/scriptify/test.env'));
        $this->assertEquals($this->read(__DIR__ . '/expected/test-with-env.service'), $this->read('/etc/systemd/system/test.service'));

        $services = Scriptify::listServices();
        $this->assertEquals(["test"], $services);

        Scriptify::uninstall('test');

        $services = Scriptify::listServices();
        $this->assertEquals([], $services);
    }

}