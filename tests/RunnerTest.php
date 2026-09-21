<?php

use \PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * A class `new` cannot build: it is the whole reason container support exists.
 */
class RunnerContainerFixture
{
    public function __construct(private string $greeting)
    {
    }

    public function greet(string $name): void
    {
        echo "{$this->greeting}, {$name}!\n";
    }
}

class RunnerTest extends TestCase
{
    /** @param array<string, mixed> $entries */
    private function container(array $entries): ContainerInterface
    {
        return new class ($entries) implements ContainerInterface {
            /** @param array<string, mixed> $entries */
            public function __construct(private array $entries)
            {
            }

            public function get(string $id): mixed
            {
                return $this->entries[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->entries);
            }
        };
    }

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

    public function testExecuteArgsWithoutRequired(): void
    {
        $this->expectException(ArgumentCountError::class);
        $runner = new \ByJG\Scriptify\Runner(
            'ByJG\Scriptify\Sample\TryMe::ping',
            [],
            false
        );
        $runner->execute();
    }

    public function testExecuteArgs(): void
    {
        $runner = new \ByJG\Scriptify\Runner(
            'ByJG\Scriptify\Sample\TryMe::ping',
            ["first"],
            false
        );
        $runner->execute();

        $this->assertTrue(file_exists('/tmp/tryme_test.txt'));
        $this->assertEquals("pong - first - \n", file_get_contents('/tmp/tryme_test.txt'));
    }


    public function testExecuteArgs2(): void
    {
        $runner = new \ByJG\Scriptify\Runner(
            'ByJG\Scriptify\Sample\TryMe::ping',
            ["first", "second"],
            false
        );
        $runner->execute();

        $this->assertTrue(file_exists('/tmp/tryme_test.txt'));
        $this->assertEquals("pong - first - second\n", file_get_contents('/tmp/tryme_test.txt'));
    }

    public function testCliArg(): void
    {
        /** @psalm-suppress ForbiddenCode */
        shell_exec( __DIR__ . '/../scripts/scriptify run \\\ByJG\\\Scriptify\\\Sample\\\TryMe::ping --arg 1 --arg 2 --rootdir ' . __DIR__ . '/..');
        $this->assertEquals("pong - 1 - 2\n", file_get_contents('/tmp/tryme_test.txt'));
    }


    /**
     * With the class registered, the container builds it -- including the
     * constructor argument that `new` has no way of supplying.
     */
    public function testContainerBuildsTheClassWhenItIsRegistered(): void
    {
        $container = $this->container([
            RunnerContainerFixture::class => new RunnerContainerFixture('Hello'),
        ]);

        $runner = new \ByJG\Scriptify\Runner(
            RunnerContainerFixture::class . '::greet',
            ['World'],
            false,
            $container
        );

        ob_start();
        $runner->execute();
        $this->assertSame("Hello, World!\n", ob_get_clean());
    }

    /**
     * Same class, no container: the fallback is `new`, so the missing constructor
     * argument still fails. This is the behaviour container support opts out of --
     * it is not silently changed for anyone else.
     */
    public function testWithoutContainerTheFallbackIsStillNew(): void
    {
        $this->expectException(ArgumentCountError::class);

        new \ByJG\Scriptify\Runner(
            RunnerContainerFixture::class . '::greet',
            ['World'],
            false
        );
    }

    /**
     * A container that does not know the class does not get in the way: a class
     * with no dependencies keeps being instantiated directly.
     */
    public function testUnknownClassFallsBackToNewEvenWithAContainer(): void
    {
        $runner = new \ByJG\Scriptify\Runner(
            'ByJG\Scriptify\Sample\TryMe::ping',
            ['first', 'second'],
            false,
            $this->container([])
        );

        ob_start();
        $runner->execute();
        $this->assertSame("pong - first - second\n", ob_get_clean());
    }
}
