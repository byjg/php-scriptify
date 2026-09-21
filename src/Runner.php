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

        $arr = explode("::", $object);
        $className = $this->className = $arr[0];
        $this->methodName = $arr[1];

        // Prepare environment
        $this->consoleArgs = $consoleArgs;

        // Instantiate the class
        $this->instance = self::resolve($className, $container);
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

        if ($container !== null && $container->has($className)) {
            return $container->get($className);
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
        $docs .= ($_SERVER['argv'][0] ?? 'scriptify') . " run \"" . str_replace('\\', '\\\\', $this->className . "::" . $this->methodName) . "\" ";

        foreach ($method->getParameters() as $param) {
            $delimiter = $param->isOptional() ? "[]" : "<>";
            $docs .= "--arg " . $delimiter[0] . $param->name .  $delimiter[1] . " ";
        }

        echo $docs;
    }
}
