<?php

namespace ByJG\Scriptify;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * A command line for the commands of a CommandProvider: `<script> <command> [args...]`.
 *
 * ```php
 * exit((new Launcher(new AppCommands(), $container))->run($argv));
 * ```
 *
 * - `<script> list` lists the commands with the first line of each method's PHPDoc, and
 *   `<script> list --check` gives each one a status without running it.
 * - `<script> <command> --help` prints the method's PHPDoc and usage.
 * - `<script> <command> a b` calls the method with "a" and "b", in order, as its
 *   parameters; `--daemon` calls it again every second, for a service.
 *
 * Without a container each class is built with `new`. With one, the container builds it,
 * and a class the container does not know is an error (see Runner::resolve()).
 *
 * A host with commands of its own extends it: it overrides dispatch() to run them,
 * isReserved() so the provider cannot take their names, and listCommands() and report()
 * to show them.
 */
class Launcher
{
    public const OK = 'OK';

    /** @var resource */
    protected $stdErr;

    protected string $scriptName = 'scriptify';

    /**
     * @param resource|null $stdErr
     */
    public function __construct(
        protected CommandProvider $commands,
        protected ?ContainerInterface $container = null,
        $stdErr = null
    ) {
        $this->stdErr = $stdErr ?? STDERR;
    }

    /**
     * @param string[] $argv The raw argument vector, script name included.
     * @return int The process exit code.
     */
    public function run(array $argv): int
    {
        $argv = array_values($argv);
        $this->scriptName = $argv[0] ?? $this->scriptName;
        $arguments = array_slice($argv, 1);
        $verbose = array_intersect($arguments, ['-v', '-vv', '-vvv']) !== [];

        try {
            $arguments = $this->prepare($arguments);
            $command = array_shift($arguments) ?? 'list';

            return $this->dispatch($command, $arguments);
        } catch (Throwable $ex) {
            fwrite($this->stdErr, $ex->getMessage() . "\n");
            if ($verbose) {
                fwrite($this->stdErr, "\n" . $ex->getTraceAsString() . "\n");
            }
            return 1;
        }
    }

    /**
     * The commands of the provider: command name => "Class::method", sorted by name.
     *
     * A target without "::" names a class: each public, non-static method it declares
     * becomes "<name>:<method-in-kebab-case>". A class that does not exist is kept as it is,
     * so it is reported as an invalid target.
     *
     * @return array<string, string>
     * @throws ScriptifyException When a name is produced twice.
     */
    public function getCommands(): array
    {
        $expanded = [];
        foreach ($this->commands->commands() as $name => $target) {
            $name = (string) $name;
            $target = (string) $target;
            foreach (self::expandClassTarget($name, $target) ?? [$name => $target] as $entryName => $entryTarget) {
                self::addCommand($expanded, $entryName, $entryTarget);
            }
        }
        ksort($expanded);

        return $expanded;
    }

    /**
     * A name the provider cannot use, because the launcher answers to it. A host with
     * commands of its own adds them.
     */
    public function isReserved(string $name): bool
    {
        return $name === 'list';
    }

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-z0-9:-]+$/', $name) === 1;
    }

    /**
     * A status per command, without running or building any of them: the check uses
     * reflection and the container's has() only.
     *
     * @return array<string, array{0: string, 1: string}> command => [status, detail]
     */
    public function check(): array
    {
        try {
            $commands = $this->getCommands();
        } catch (Throwable $ex) {
            return [get_class($this->commands) => ['ERROR', self::oneLine($ex->getMessage())]];
        }
        if ($commands === []) {
            return [];
        }

        // Obtained once, for has(). A failure here is reported on every command.
        try {
            $container = $this->getContainer();
        } catch (Throwable $ex) {
            return array_fill_keys(array_keys($commands), ['ERROR', self::oneLine($ex->getMessage())]);
        }

        $result = [];
        foreach ($commands as $name => $target) {
            try {
                $result[$name] = [$this->status($name, $target, $container), $target];
            } catch (Throwable $ex) {
                $result[$name] = ['ERROR', self::oneLine($ex->getMessage())];
            }
        }

        return $result;
    }

    /**
     * The arguments before the command is read. A host consumes its global options here.
     *
     * @param string[] $arguments
     * @return string[]
     */
    protected function prepare(array $arguments): array
    {
        return $arguments;
    }

    /**
     * @param string[] $arguments
     * @throws Throwable
     */
    protected function dispatch(string $command, array $arguments): int
    {
        if (in_array($command, ['list', '--help', '-h'], true)) {
            return in_array('--check', $arguments, true) ? $this->report() : $this->listCommands();
        }

        return $this->runCommand($command, $arguments);
    }

    /**
     * @param string[] $arguments
     * @throws Throwable
     */
    protected function runCommand(string $name, array $arguments): int
    {
        $commands = $this->getCommands();
        if (!isset($commands[$name])) {
            throw new ScriptifyException("Command \"$name\" is not defined. Run {$this->scriptName()} list to see the available commands.");
        }
        if ($this->isReserved($name)) {
            throw $this->reservedName($name);
        }
        if (!self::isValidName($name)) {
            throw new ScriptifyException("Invalid command name \"$name\": only lowercase letters, digits, ':' and '-' are allowed.");
        }
        $target = $commands[$name];
        if (!str_contains($target, '::')) {
            throw new ScriptifyException("Invalid target for \"$name\" in " . get_class($this->commands) . ': expected "Namespace/Class::method" or an existing "Namespace/Class".');
        }

        $wantsHelp = array_intersect($arguments, ['--help', '-h']) !== [];
        $daemon = in_array('--daemon', $arguments, true);
        // These belong to the launcher, not to the method.
        $arguments = array_values(array_diff($arguments, ['-v', '-vv', '-vvv', '--help', '-h', '--daemon']));

        $runner = new Runner($target, $arguments, $daemon, $this->getContainer());
        if ($wantsHelp) {
            $runner->showDocs($this->scriptName() . " $name");
            echo "\n";
            return 0;
        }
        $runner->execute();

        return 0;
    }

    protected function reservedName(string $name): ScriptifyException
    {
        return new ScriptifyException("\"$name\" is reserved and cannot be used as a command name. Rename it in " . get_class($this->commands) . '.');
    }

    /**
     * The container that builds the classes, or null to build them with `new`. A host that
     * builds its container on demand overrides it.
     */
    protected function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * The name the usage and help lines show.
     */
    protected function scriptName(): string
    {
        return $this->scriptName;
    }

    protected function usage(): string
    {
        $script = $this->scriptName();

        return "Usage:\n  $script <command> [args...]\n  $script <command> --help\n  $script list [--check]\n";
    }

    protected function listCommands(): int
    {
        echo $this->usage() . "\n";
        $this->printCommands('Commands');

        return 0;
    }

    /**
     * The commands with the first line of their PHPDoc, or the target when there is none.
     */
    protected function printCommands(string $title): void
    {
        echo "$title\n";
        $commands = $this->getCommands();
        foreach ($commands as $name => $target) {
            $summary = self::getSummary($target);
            echo self::formatRow($name, $summary !== '' ? $summary : $target);
        }
        if ($commands === []) {
            echo "  (none: add them to " . get_class($this->commands) . ")\n";
        }
    }

    /**
     * Print the status of every command. Returns 0 when every command is OK, 1 otherwise.
     */
    protected function report(): int
    {
        return $this->printReport('Commands', $this->check()) ? 0 : 1;
    }

    /**
     * @param array<array-key, array{0: string, 1: string}> $rows command => [status, detail]
     * @return bool Whether every row is OK.
     */
    protected function printReport(string $title, array $rows): bool
    {
        echo "$title\n";
        $allOk = true;
        foreach ($rows as $name => [$status, $detail]) {
            echo self::formatRow((string) $name, str_pad($status, 17), $detail);
            $allOk = $allOk && $status === self::OK;
        }
        if ($rows === []) {
            echo "  (none)\n";
        }

        return $allOk;
    }

    protected function status(string $name, string $target, ?ContainerInterface $container): string
    {
        if (!self::isValidName($name)) {
            return 'INVALID_NAME';
        }
        if ($this->isReserved($name)) {
            return 'RESERVED';
        }
        if (preg_match('~^[^:\s]+::[A-Za-z_]\w*$~', $target) !== 1) {
            return 'INVALID_TARGET';
        }

        [$class, $method] = explode('::', ltrim(Runner::normalize($target), '\\'));
        if (!class_exists($class)) {
            return 'MISSING_CLASS';
        }
        if (!method_exists($class, $method)) {
            return 'MISSING_METHOD';
        }
        if (!(new ReflectionMethod($class, $method))->isPublic()) {
            return 'NOT_PUBLIC';
        }
        if ($container !== null && !$container->has($class)) {
            return 'NOT_IN_CONTAINER';
        }

        return self::OK;
    }

    public static function formatRow(string ...$columns): string
    {
        $row = '  ' . str_pad((string) array_shift($columns), 22);
        foreach ($columns as $column) {
            $row .= ' ' . $column;
        }

        return rtrim($row) . "\n";
    }

    /**
     * @param array<string, string> $commands
     * @throws ScriptifyException When the name is already there.
     */
    protected static function addCommand(array &$commands, string $name, string $target): void
    {
        if (array_key_exists($name, $commands)) {
            throw new ScriptifyException("Command \"$name\" is defined more than once.");
        }
        $commands[$name] = $target;
    }

    /**
     * @return array<string, string>|null The commands of a class target; null for any other target.
     * @throws ScriptifyException When two methods give the same command name.
     */
    protected static function expandClassTarget(string $name, string $target): ?array
    {
        if (str_contains($target, '::')) {
            return null;
        }
        $class = ltrim(Runner::normalize($target), '\\');
        if (!class_exists($class)) {
            return null;
        }

        $entries = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getDeclaringClass()->getName() !== $class || str_starts_with($method->getName(), '__')) {
                continue;
            }
            self::addCommand($entries, $name . ':' . self::toKebabCase($method->getName()), $class . '::' . $method->getName());
        }

        return $entries;
    }

    /**
     * The first line of the target method's PHPDoc, or "" when there is none or the target
     * does not resolve to a method.
     */
    protected static function getSummary(string $target): string
    {
        if (!str_contains($target, '::')) {
            return '';
        }
        [$class, $method] = explode('::', ltrim(Runner::normalize($target), '\\'), 2) + ['', ''];
        if (!class_exists($class) || !method_exists($class, $method)) {
            return '';
        }

        foreach (explode("\n", (string) (new ReflectionMethod($class, $method))->getDocComment()) as $line) {
            $line = trim((string) preg_replace('~^\s*(/\*\*|\*/|\*)~', '', $line));
            $line = trim((string) preg_replace('~\*/$~', '', $line));
            if ($line !== '') {
                return str_starts_with($line, '@') ? '' : $line;
            }
        }

        return '';
    }

    protected static function toKebabCase(string $method): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', str_replace('_', '-', $method)));
    }

    protected static function oneLine(string $message): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $message));
    }
}
