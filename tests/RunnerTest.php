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
     * The documented way of naming a class carries a leading backslash
     * ("\\Some\\Class::method"). class_exists() accepts it, so without
     * normalisation the container would miss every entry and fall back to `new`
     * silently -- the failure would look like "the container is not being used".
     */
    public function testLeadingBackslashStillFindsTheContainerEntry(): void
    {
        $container = $this->container([
            RunnerContainerFixture::class => new RunnerContainerFixture('Hello'),
        ]);

        $runner = new \ByJG\Scriptify\Runner(
            '\\' . RunnerContainerFixture::class . '::greet',
            ['World'],
            false,
            $container
        );

        ob_start();
        $runner->execute();
        $this->assertSame("Hello, World!\n", ob_get_clean());
    }

    /**
     * Same class, no container: `new` cannot supply the constructor argument, and
     * Scriptify says so instead of letting an ArgumentCountError escape from its
     * own guts. The message has to name the class and point at the way out --
     * the previous error named a class that may well be registered under an id
     * nobody looked up, which reads as "the container is being ignored".
     */
    public function testConstructorArgumentsWithoutAContainerFailWithAClearMessage(): void
    {
        $this->expectException(\ByJG\Scriptify\ScriptifyException::class);
        $this->expectExceptionMessage('no PSR-11 container was offered');

        new \ByJG\Scriptify\Runner(
            RunnerContainerFixture::class . '::greet',
            ['World'],
            false
        );
    }

    /**
     * Container present but without the entry: the message says *that*, because
     * the fix is a binding, not a bootstrap.
     */
    public function testMissingContainerEntrySaysWhichIdWasTried(): void
    {
        $this->expectException(\ByJG\Scriptify\ScriptifyException::class);
        $this->expectExceptionMessage('the container has no entry for "' . RunnerContainerFixture::class . '"');

        new \ByJG\Scriptify\Runner(
            '\\' . RunnerContainerFixture::class . '::greet',
            ['World'],
            false,
            $this->container([])
        );
    }

    /**
     * Offering a container makes it the way objects are built, full stop. Even a
     * class `new` could build is refused when the container does not have it:
     * silently building it would be a guess, and a class whose dependencies are
     * all optional would run without the collaborators the container holds --
     * working, wrong, and quiet about it.
     */
    public function testContainerMissIsRefusedEvenWhenNewWouldHaveWorked(): void
    {
        $this->expectException(\ByJG\Scriptify\ScriptifyException::class);
        $this->expectExceptionMessage('the container has no entry for "ByJG\Scriptify\Sample\TryMe"');

        new \ByJG\Scriptify\Runner(
            'ByJG\Scriptify\Sample\TryMe::ping',
            ['first', 'second'],
            false,
            $this->container([])
        );
    }

    /**
     * And without a container nothing changed: the same class is built with new.
     */
    public function testWithoutAContainerASimpleClassIsStillBuiltWithNew(): void
    {
        $runner = new \ByJG\Scriptify\Runner(
            'ByJG\Scriptify\Sample\TryMe::ping',
            ['first', 'second'],
            false
        );

        ob_start();
        $runner->execute();
        $this->assertSame("pong - first - second\n", ob_get_clean());
    }
}
