<?php

namespace Tests;

use ByJG\Scriptify\Console\RunCommand;
use ByJG\Scriptify\ScriptifyException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * `scriptify run --bootstrap`: the default is the autoload file, and the class is built with
 * `new`. A bootstrap that returns a PSR-11 container hands it over, and the container then
 * has to hold the class.
 */
class RunCommandTest extends TestCase
{
    protected string $rootDir;
    protected string $cwd;

    #[\Override]
    protected function setUp(): void
    {
        $this->cwd = (string)getcwd();
        $this->rootDir = sys_get_temp_dir() . '/scriptify-run-' . uniqid();
        mkdir($this->rootDir);
    }

    #[\Override]
    protected function tearDown(): void
    {
        chdir($this->cwd);
        foreach (glob($this->rootDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->rootDir);
    }

    /**
     * A bootstrap returning a container that holds TryMe, or nothing; it says when it builds.
     */
    protected function writeContainerBootstrap(bool $holdsTryMe): string
    {
        file_put_contents(
            "$this->rootDir/bootstrap.php",
            '<?php return new class implements \Psr\Container\ContainerInterface {'
            . ' public function get(string $id): mixed { echo "built by container\n"; return new \ByJG\Scriptify\Sample\TryMe(); }'
            . ' public function has(string $id): bool { return ' . var_export($holdsTryMe, true) . ' && $id === \ByJG\Scriptify\Sample\TryMe::class; }'
            . ' };'
        );

        return 'bootstrap.php';
    }

    protected function runPing(string $bootstrap, string $rootDir): string
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand(new RunCommand());

        ob_start();
        try {
            $application->run(new ArrayInput([
                'command' => 'run',
                'classname' => 'ByJG/Scriptify/Sample/TryMe::ping',
                '--bootstrap' => $bootstrap,
                '--rootdir' => $rootDir,
                '--arg' => ['hello'],
            ]), new BufferedOutput());
        } finally {
            $stdOut = (string)ob_get_clean();
        }

        return $stdOut;
    }

    public function testTheDefaultBootstrapBuildsTheClassWithNew(): void
    {
        $this->assertSame("pong - hello - \n", $this->runPing('vendor/autoload.php', $this->cwd));
    }

    public function testABootstrapThatReturnsAContainerHasTheClassBuiltByIt(): void
    {
        $this->assertSame(
            "built by container\npong - hello - \n",
            $this->runPing($this->writeContainerBootstrap(true), $this->rootDir)
        );
    }

    public function testAContainerWithoutTheClassIsRefusedNotBuiltWithNew(): void
    {
        $this->expectException(ScriptifyException::class);
        $this->expectExceptionMessage('the container has no entry for "ByJG\Scriptify\Sample\TryMe"');

        $this->runPing($this->writeContainerBootstrap(false), $this->rootDir);
    }
}
