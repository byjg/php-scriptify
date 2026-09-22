<?php

namespace ByJG\Scriptify;

use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

class Runner
{

    const SLEEP_SERVICE = 1000;

    const BASE_LOG_PATH = "/var/log/scriptify";

    protected $stdIn = STDIN;
    protected $stdOut = STDOUT;
    protected $stdErr = STDERR;

    protected ?string $className = null;
    protected ?string $methodName = null;
    protected mixed $instance = null;

    protected bool $daemon = true;

    protected array $consoleArgs = [];

    public function __construct(
        string $object,
        array $consoleArgs = [],
        bool $daemon = true,
        ?ContainerInterface $container = null
    ) {
        $this->daemon = $daemon;

        $arr = explode("::", self::normalize($object));
        $className = $this->className = $arr[0];
        $this->methodName = $arr[1];

        // Prepare environment
        $this->consoleArgs = $consoleArgs;

        // Instantiate the class
        $this->instance = self::resolve($className, $container);
    }

    /**
     * Accept "Name/Space/Class::method" as well as "Name\Space\Class::method".
     *
     * A backslash is the shell's escape character, so the documented form has to
     * be quoted -- and doubled again once it passes through another layer. A
     * class named inside a composer script or a JSON manifest can end up with
     * four backslashes per separator, which is a fine place for a typo to hide.
     * A forward slash needs no escaping anywhere, and cannot be mistaken for
     * anything else: it is not valid in a PHP identifier or namespace.
     */
    public static function normalize(string $object): string
    {
        return str_replace('/', '\\', $object);
    }

    /**
     * Build the object whose method will be called.
     *
     * Without a container this is `new $className()`, which is what Scriptify has
     * always done: a class with no constructor dependencies needs no container at
     * all. When a PSR-11 container is given and knows the class, the container
     * wins -- that is what allows a class with constructor dependencies to be
     * scriptified without changing it.
     *
     * A class the container does not know falls back to `new`. Errors thrown by
     * the container are NOT caught: an entry that exists but is misconfigured is a
     * configuration problem, and hiding it behind `new` would surface much later,
     * as a confusing error somewhere else.
     *
     * @throws Exception
     */
    public static function resolve(string $className, ?ContainerInterface $container = null): mixed
    {
        if (!class_exists($className)) {
            throw new \Exception("Could not found the class $className");
        }

        // Scriptify's own convention writes the class with a leading backslash
        // ("\\Some\\Class::method", as every example in the docs does) and
        // class_exists() accepts it. A PSR-11 id does not carry one -- ::class
        // never produces it -- so passing the name through unchanged would miss
        // every container entry and fall back to `new` without a word.
        $containerId = ltrim($className, '\\');

        // Offering a container is a statement: "build my objects this way".
        // Scriptify takes it literally and does not second-guess a miss with
        // `new`. Guessing would build the object anyway, with whatever `new`
        // gives it -- a class with only optional dependencies would run without
        // the collaborators the container would have injected, and nothing would
        // say so.
        if ($container !== null) {
            if (!$container->has($containerId)) {
                throw new ScriptifyException(
                    sprintf(
                        'Cannot build %s: the container has no entry for "%s". Register it, or '
                        . 'drop the container from the --bootstrap file to have Scriptify build '
                        . 'it with new.',
                        $containerId,
                        $containerId
                    )
                );
            }

            return $container->get($containerId);
        }

        // No container: `new`, as Scriptify has always done -- and a clear error
        // when `new` cannot do it, instead of an ArgumentCountError thrown from
        // inside Scriptify with a stack trace pointing at the wrong place.
        $constructor = (new ReflectionClass($className))->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new ScriptifyException(
                sprintf(
                    'Cannot build %s: its constructor requires %d argument(s), and no PSR-11 '
                    . 'container was offered. Point --bootstrap at a file that returns one.',
                    $containerId,
                    $constructor->getNumberOfRequiredParameters()
                )
            );
        }

        return new $className();
    }

    public function execute(): void
    {
        $instance = $this->instance;
        $method = $this->methodName;

        $continue = true;

        // Execute routine
        while ($continue) {
            call_user_func_array([$instance, $method], $this->consoleArgs);
            $continue = $this->daemon;

            if ($continue) {
                usleep(self::SLEEP_SERVICE * 1000);
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    public function showDocs(): void
    {
        $reflection = new ReflectionClass($this->instance);
        $method = $reflection->getMethod($this->methodName ?? '');
        $docs = $method->getDocComment();

        $docs = preg_replace('/( *\/\*\*[\r\n]| *\*\/? *)/', '', $docs);

        // get the current script name
        $docs .= "\nUsage: \n";
        // Prints the slash form: it needs no quoting, so the line can be pasted
        // as-is into a shell, a composer script or a service definition.
        $docs .= ($_SERVER['argv'][0] ?? 'scriptify') . " run "
            . str_replace('\\', '/', ltrim((string) $this->className, '\\') . "::" . $this->methodName) . " ";

        foreach ($method->getParameters() as $param) {
            $delimiter = $param->isOptional() ? "[]" : "<>";
            $docs .= "--arg " . $delimiter[0] . $param->name .  $delimiter[1] . " ";
        }

        echo $docs;
    }
}
