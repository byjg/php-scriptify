<?php

namespace Tests;

use ByJG\Scriptify\Launcher;
use ByJG\Scriptify\ScriptifyException;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\ArrayCommands;
use Tests\Fixture\ArrayContainer;
use Tests\Fixture\LauncherChildCommands;
use Tests\Fixture\LauncherCollidingCommands;
use Tests\Fixture\LauncherCommands;
use Tests\Fixture\LauncherPlainCommands;

class LauncherTest extends TestCase
{
    /** @var resource */
    protected $stdErr;

    #[\Override]
    protected function setUp(): void
    {
        $this->stdErr = fopen('php://memory', 'w+');
    }

    #[\Override]
    protected function tearDown(): void
    {
        fclose($this->stdErr);
    }

    /**
     * The container knows LauncherCommands only.
     */
    protected function container(): ArrayContainer
    {
        return new ArrayContainer([LauncherCommands::class => fn () => new LauncherCommands('built by container')]);
    }

    /**
     * @param array<string, string> $commands
     */
    protected function launcher(array $commands, ?ArrayContainer $container = null): Launcher
    {
        return new Launcher(new ArrayCommands($commands), $container, $this->stdErr);
    }

    /**
     * Run `./app <arguments>` and return [exit code, stdout, stderr].
     *
     * @return array{0: int, 1: string, 2: string}
     */
    protected function app(Launcher $launcher, string ...$arguments): array
    {
        ftruncate($this->stdErr, 0);
        rewind($this->stdErr);

        ob_start();
        try {
            $exitCode = $launcher->run(['./app', ...$arguments]);
        } finally {
            $stdOut = (string) ob_get_clean();
        }
        rewind($this->stdErr);

        return [$exitCode, $stdOut, (string) stream_get_contents($this->stdErr)];
    }

    public function testListShowsTheSummaryOrTheTarget(): void
    {
        [$exitCode, $stdOut] = $this->app($this->launcher([
            'user:cleanup' => LauncherCommands::class . '::cleanOldRecords',
            'user:fail' => LauncherCommands::class . '::fail',
        ]));

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString("  ./app <command> --help\n", $stdOut);
        $this->assertMatchesRegularExpression('/^  user:cleanup +Delete records older than the given number of days\.$/m', $stdOut);
        $this->assertMatchesRegularExpression('/^  user:fail +' . preg_quote(LauncherCommands::class, '/') . '::fail$/m', $stdOut);
    }

    public function testListWithoutCommandsSaysWhereToAddThem(): void
    {
        [, $stdOut] = $this->app($this->launcher([]), 'list');

        $this->assertStringContainsString('(none: add them to ' . ArrayCommands::class . ')', $stdOut);
    }

    public function testACommandRunsThroughTheContainerWithPositionalArguments(): void
    {
        $container = $this->container();
        [$exitCode, $stdOut] = $this->app(
            $this->launcher(['user:cleanup' => LauncherCommands::class . '::cleanOldRecords'], $container),
            'user:cleanup', '30', 'users'
        );

        $this->assertSame(0, $exitCode);
        $this->assertSame("built by container: days=30 table='users'\n", $stdOut);
        $this->assertSame(1, $container->built);
    }

    public function testWithoutAContainerTheClassIsBuiltWithNew(): void
    {
        [$exitCode, $stdOut] = $this->app(
            $this->launcher(['hello' => 'Tests/Fixture/LauncherPlainCommands::greet']),
            'hello', 'you'
        );

        $this->assertSame(0, $exitCode);
        $this->assertSame("hello you\n", $stdOut);
    }

    public function testAClassTheContainerDoesNotHaveIsRefusedNotBuiltWithNew(): void
    {
        [$exitCode, $stdOut, $stdErr] = $this->app(
            $this->launcher(['hello' => LauncherPlainCommands::class . '::greet'], $this->container()),
            'hello'
        );

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $stdOut);
        $this->assertStringContainsString('the container has no entry for "' . LauncherPlainCommands::class . '"', $stdErr);
    }

    public function testAClassTargetExposesEachPublicMethodInKebabCase(): void
    {
        $launcher = $this->launcher(['records' => LauncherCommands::class]);

        $this->assertSame(
            [
                'records:clean-old-records' => LauncherCommands::class . '::cleanOldRecords',
                'records:fail' => LauncherCommands::class . '::fail',
            ],
            $launcher->getCommands()
        );

        [$exitCode, $stdOut] = $this->app(
            $this->launcher(['records' => LauncherCommands::class], $this->container()),
            'records:clean-old-records', '7'
        );
        $this->assertSame(0, $exitCode);
        $this->assertSame("built by container: days=7 table=NULL\n", $stdOut);
    }

    public function testAClassTargetSkipsInheritedStaticAndMagicMethods(): void
    {
        $this->assertSame(
            ['child:own-command' => LauncherChildCommands::class . '::ownCommand'],
            $this->launcher(['child' => 'Tests/Fixture/LauncherChildCommands'])->getCommands()
        );
    }

    public function testAClassTargetCollidingWithAnExplicitCommandIsRefused(): void
    {
        $this->expectException(ScriptifyException::class);
        $this->expectExceptionMessage('Command "records:fail" is defined more than once.');

        $this->launcher([
            'records' => LauncherCommands::class,
            'records:fail' => LauncherCommands::class . '::fail',
        ])->getCommands();
    }

    public function testTwoMethodsWithTheSameCommandNameAreRefused(): void
    {
        $this->expectException(ScriptifyException::class);
        $this->expectExceptionMessage('Command "mail:send-mail" is defined more than once.');

        $this->launcher(['mail' => LauncherCollidingCommands::class])->getCommands();
    }

    public function testHelpPrintsTheDocsInTheLauncherSyntaxWithoutRunning(): void
    {
        $container = $this->container();
        [$exitCode, $stdOut] = $this->app(
            $this->launcher(['user:cleanup' => LauncherCommands::class . '::cleanOldRecords'], $container),
            'user:cleanup', '--help'
        );

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Delete records older than the given number of days.', $stdOut);
        $this->assertStringContainsString('./app user:cleanup <days> [table]', $stdOut);
        $this->assertStringNotContainsString('days=', $stdOut);
    }

    public function testUnknownCommand(): void
    {
        [$exitCode, , $stdErr] = $this->app($this->launcher([]), 'nope');

        $this->assertSame(1, $exitCode);
        $this->assertSame("Command \"nope\" is not defined. Run ./app list to see the available commands.\n", $stdErr);
    }

    public function testInvalidNameAndInvalidTargetAreRefused(): void
    {
        $launcher = $this->launcher([
            'User:Cleanup' => LauncherCommands::class . '::cleanOldRecords',
            'broken' => 'Not/A/Class',
        ]);

        [$exitCode, , $stdErr] = $this->app($launcher, 'User:Cleanup');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid command name "User:Cleanup"', $stdErr);

        [$exitCode, , $stdErr] = $this->app($launcher, 'broken');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid target for "broken"', $stdErr);
    }

    public function testAReservedNameIsRefused(): void
    {
        $launcher = new class (new ArrayCommands(['deploy' => LauncherPlainCommands::class . '::greet']), null, $this->stdErr) extends Launcher {
            #[\Override]
            public function isReserved(string $name): bool
            {
                return $name === 'deploy' || parent::isReserved($name);
            }
        };

        [$exitCode, $stdOut, $stdErr] = $this->app($launcher, 'deploy');

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $stdOut);
        $this->assertStringContainsString('"deploy" is reserved and cannot be used as a command name.', $stdErr);
        $this->assertSame('RESERVED', $launcher->check()['deploy'][0]);
    }

    public function testAFailureGoesToStdErrWithTheTraceOnlyWhenVerbose(): void
    {
        $launcher = $this->launcher(['user:fail' => LauncherCommands::class . '::fail'], $this->container());

        [$exitCode, , $stdErr] = $this->app($launcher, 'user:fail');
        $this->assertSame(1, $exitCode);
        $this->assertSame("Something went wrong\n", $stdErr);

        [, , $stdErr] = $this->app($launcher, 'user:fail', '-v');
        $this->assertStringContainsString("Something went wrong\n\n#0 ", $stdErr);
    }

    public function testCheckGivesEachCommandAStatusWithoutBuildingIt(): void
    {
        $container = $this->container();
        $launcher = $this->launcher([
            'ok' => LauncherCommands::class . '::cleanOldRecords',
            'Bad-Name' => LauncherCommands::class . '::cleanOldRecords',
            'list' => LauncherCommands::class . '::cleanOldRecords',
            'no-colons' => 'Not/A/Class',
            'missing-class' => 'Not/A/Class::method',
            'missing-method' => LauncherCommands::class . '::nope',
            'not-public' => LauncherCommands::class . '::hidden',
            'not-in-container' => LauncherPlainCommands::class . '::greet',
        ], $container);

        $statuses = array_map(fn (array $row): string => $row[0], $launcher->check());

        $this->assertSame([
            'Bad-Name' => 'INVALID_NAME',
            'list' => 'RESERVED',
            'missing-class' => 'MISSING_CLASS',
            'missing-method' => 'MISSING_METHOD',
            'no-colons' => 'INVALID_TARGET',
            'not-in-container' => 'NOT_IN_CONTAINER',
            'not-public' => 'NOT_PUBLIC',
            'ok' => 'OK',
        ], $statuses);
        $this->assertSame(0, $container->built);
    }

    public function testCheckWithoutAContainerDoesNotAskForOne(): void
    {
        $this->assertSame(
            ['hello' => ['OK', LauncherPlainCommands::class . '::greet']],
            $this->launcher(['hello' => LauncherPlainCommands::class . '::greet'])->check()
        );
    }

    public function testListCheckExitsOneWhenACommandIsNotOk(): void
    {
        [$exitCode, $stdOut] = $this->app($this->launcher([
            'hello' => LauncherPlainCommands::class . '::greet',
            'broken' => 'Not/A/Class::method',
        ]), 'list', '--check');

        $this->assertSame(1, $exitCode);
        $this->assertMatchesRegularExpression('/^  broken +MISSING_CLASS +Not\/A\/Class::method$/m', $stdOut);
        $this->assertMatchesRegularExpression('/^  hello +OK +/m', $stdOut);
    }

    public function testAContainerFailureMarksEveryCommand(): void
    {
        $launcher = new class (new ArrayCommands(['hello' => LauncherPlainCommands::class . '::greet'])) extends Launcher {
            #[\Override]
            protected function getContainer(): ?\Psr\Container\ContainerInterface
            {
                throw new \RuntimeException("The container\ncould not be built");
            }
        };

        $this->assertSame(['hello' => ['ERROR', 'The container could not be built']], $launcher->check());
    }
}
