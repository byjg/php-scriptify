<?php

namespace ByJG\Scriptify\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TerminalCommand extends Command
{
    /**
     * Track user-defined variables during REPL session
     */
    protected array $userVariables = [];

    /**
     * Track variable types/values for object method completion
     * Format: ['varname' => object_instance] (without $ prefix)
     */
    protected array $variableValues = [];

    /**
     * Store REPL context variables that persist across eval() calls
     * Format: ['varname' => value] (without $ prefix)
     */
    protected array $replContext = [];

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('terminal')
            ->setDescription('Open an interactive PHP terminal with autoloader and environment variables')
            ->addArgument(
                'service',
                InputArgument::OPTIONAL,
                'Optional service name to load environment variables from'
            )
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Set environment variables (can be used multiple times)',
                []
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceName = $input->getArgument('service');
        $envOptions = $input->getOption('env');

        // Load environment variables
        $environment = $this->loadEnvironment($serviceName, $envOptions, $output);

        // Discover autoload file
        $autoloadPath = $this->discoverAutoload($output);
        if ($autoloadPath === null) {
            $output->writeln('<error>Could not find autoload file. Set SCRIPTIFY_BOOTLOADER or ensure vendor/autoload.php exists.</error>');
            return 1;
        }

        $output->writeln("<info>Starting interactive PHP terminal...</info>");
        $output->writeln("<comment>Autoloader: $autoloadPath</comment>");

        if (!empty($environment)) {
            $output->writeln("<comment>Environment variables loaded: " . count($environment) . "</comment>");
        }

        $output->writeln("");

        // Set environment variables in current process
        foreach ($environment as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }

        // Load the autoloader
        require_once $autoloadPath;

        // Check if readline is available
        if (!function_exists('readline')) {
            $output->writeln("<error>readline extension is not available. Cannot start interactive terminal.</error>");
            $output->writeln("<comment>Install readline extension or use 'php -a' directly.</comment>");
            return 1;
        }

        // Start custom REPL with prompt
        return $this->startRepl($output);
    }

    /**
     * Load environment variables from service file and/or command options
     */
    protected function loadEnvironment(?string $serviceName, array $envOptions, OutputInterface $output): array
    {
        $environment = [];

        // Load from service file if service name provided
        if ($serviceName !== null) {
            $envFile = "/etc/scriptify/{$serviceName}.env";

            if (file_exists($envFile)) {
                $output->writeln("<info>Loading environment from: $envFile</info>");
                $environment = $this->parseEnvFile($envFile);
            } else {
                $output->writeln("<comment>Warning: Service environment file not found: $envFile</comment>");
            }
        }

        // Parse and merge command-line env options
        if (!empty($envOptions)) {
            parse_str(implode("&", $envOptions), $cmdEnv);
            $environment = array_merge($environment, $cmdEnv);
        }

        return $environment;
    }

    /**
     * Parse environment file (format: export KEY=VALUE)
     */
    protected function parseEnvFile(string $filePath): array
    {
        $environment = [];
        $content = file_get_contents($filePath);

        if ($content === false) {
            return $environment;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Remove 'export ' prefix if present
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    /**
     * Discover autoload file location
     */
    protected function discoverAutoload(OutputInterface $output): ?string
    {
        // Check SCRIPTIFY_BOOTLOADER environment variable
        $autoload = getenv('SCRIPTIFY_BOOTLOADER');

        if (!empty($autoload) && file_exists($autoload)) {
            $resolved = realpath($autoload);
            return $resolved !== false ? $resolved : null;
        }

        // Try default locations
        $defaultPaths = [
            getcwd() . '/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../../../../autoload.php'
        ];

        foreach ($defaultPaths as $path) {
            if (file_exists($path)) {
                $resolved = realpath($path);
                return $resolved !== false ? $resolved : null;
            }
        }

        return null;
    }

    /**
     * Start a custom REPL (Read-Eval-Print Loop) with prompt
     */
    protected function startRepl(OutputInterface $output): int
    {
        echo "Scriptify Interactive Terminal\n";
        echo "Type 'exit' or press Ctrl+D to quit\n";
        echo "\n";

        // Enable readline history and smart autocompletion
        $self = $this;

        // Store reference for completion function
        /**
         * @param string $input
         * @return array<array-key, string>
         */
        $completionCallback = function(string $input) use ($self): array {

            // Check if we're completing a variable or namespace (look at the line buffer)
            $info = readline_info();
            $line = $info['line_buffer'] ?? '';
            $point = $info['point'] ?? 0;

            // Check for object method/property completion (->)
            // Look at the entire line buffer before cursor
            $beforeCursor = substr($line, 0, $point);

            // Match patterns like: $var-> or $var->met
            if (preg_match('/(\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*->\s*$/', $beforeCursor, $matches)) {
                // Case 1: Just typed $var-> with nothing after
                $varName = $matches[1];
                $methodPrefix = $input; // Use the input as prefix (might be empty)

                // Get methods/properties for this variable
                $completions = $self->getObjectCompletions($varName, $methodPrefix);
                return $completions;
            } elseif (preg_match('/(\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*->\s*([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]+)$/', $beforeCursor, $matches)) {
                // Case 2: Typed $var->methodName (with some letters)
                $varName = $matches[1];
                $methodPrefix = $matches[2];

                // Get methods/properties for this variable
                $completions = $self->getObjectCompletions($varName, $methodPrefix);
                return $completions;
            }

            // Extract the full word including namespace separators and $ signs
            // Look backwards from cursor to find the complete word
            $startPos = $point - strlen($input);
            $prefix = '';

            // Scan backwards to capture $ or namespace parts
            while ($startPos > 0) {
                $prevChar = $line[$startPos - 1];
                if ($prevChar === '$') {
                    // Variable completion
                    $prefix = '$' . $prefix;
                    $startPos--;
                    break;
                } elseif ($prevChar === '\\') {
                    // Namespace separator - continue scanning backwards
                    $prefix = '\\' . $prefix;
                    $startPos--;

                    // Find the part before the backslash
                    $segmentStart = $startPos;
                    while ($segmentStart > 0 && ctype_alnum($line[$segmentStart - 1])) {
                        $segmentStart--;
                    }

                    // Extract the segment
                    if ($segmentStart < $startPos) {
                        $segment = substr($line, $segmentStart, $startPos - $segmentStart);
                        $prefix = $segment . $prefix;
                        $startPos = $segmentStart;
                    }
                } else {
                    break;
                }
            }

            $actualInput = $prefix . $input;

            // If actualInput is still empty after all processing, return empty
            if (empty($actualInput)) {
                return [];
            }

            // Get completions based on the actual input word
            $completions = $self->getCompletions($actualInput);

            // Remove the prefix from completions since readline will add it back
            if ($prefix !== '') {
                $prefixLen = strlen($prefix);
                $completions = array_map(function($item) use ($prefixLen) {
                    return substr($item, $prefixLen);
                }, $completions);
            }

            // Return completions for readline
            return $completions;
        };

        readline_completion_function($completionCallback);

        $buffer = '';
        $inMultiline = false;

        while (true) {
            // Determine prompt based on whether we're in multiline mode
            $prompt = $inMultiline ? 'php* ' : 'php> ';

            // Read line from user
            $line = readline($prompt);

            // Handle Ctrl+D (EOF)
            if ($line === false) {
                echo "\n";
                break;
            }

            // Add to history (skip empty lines)
            if (trim($line) !== '') {
                readline_add_history($line);
            }

            // Handle exit command
            if (trim($line) === 'exit' || trim($line) === 'quit') {
                break;
            }

            // Accumulate buffer
            $buffer .= $line . "\n";

            // Check if we have a complete statement
            // Simple heuristic: check for unclosed braces, brackets, parentheses
            $openBraces = substr_count($buffer, '{') - substr_count($buffer, '}');
            $openBrackets = substr_count($buffer, '[') - substr_count($buffer, ']');
            $openParens = substr_count($buffer, '(') - substr_count($buffer, ')');

            // Check for unclosed strings (basic check)
            $singleQuotes = substr_count($buffer, "'") - substr_count($buffer, "\\'");
            $doubleQuotes = substr_count($buffer, '"') - substr_count($buffer, '\\"');

            if ($openBraces > 0 || $openBrackets > 0 || $openParens > 0 ||
                ($singleQuotes % 2 !== 0) || ($doubleQuotes % 2 !== 0)) {
                $inMultiline = true;
                continue;
            }

            $inMultiline = false;

            // Trim and check if buffer is empty
            $code = trim($buffer);
            if ($code === '') {
                $buffer = '';
                continue;
            }

            // Wrap code in return if it's an expression (doesn't end with semicolon or brace)
            $lastChar = substr($code, -1);
            $wrappedCode = $code;
            if ($lastChar !== ';' && $lastChar !== '}') {
                $wrappedCode = "return ($code);";
            }

            // Extract and track variables from the original code (before wrapping)
            $this->extractVariablesFromCode($code);

            // Execute the code with persistent context
            try {
                // Extract REPL context variables into local scope
                extract($this->replContext);

                // Execute the code
                $result = eval($wrappedCode);

                // Capture all defined variables after execution
                $currentVars = get_defined_vars();

                // Remove internal variables from the capture
                unset($currentVars['result'], $currentVars['wrappedCode'], $currentVars['code'],
                      $currentVars['this'], $currentVars['output'], $currentVars['input'],
                      $currentVars['buffer'], $currentVars['inMultiline'], $currentVars['line'],
                      $currentVars['prompt'], $currentVars['lastChar'], $currentVars['openBraces'],
                      $currentVars['openBrackets'], $currentVars['openParens'], $currentVars['singleQuotes'],
                      $currentVars['doubleQuotes'], $currentVars['self'], $currentVars['completionCallback'],
                      $currentVars['serviceName'], $currentVars['envOptions'], $currentVars['environment'],
                      $currentVars['autoloadPath'], $currentVars['currentVars']);

                // Update the REPL context with all user-defined variables
                $this->replContext = $currentVars;

                // Update variableValues for object completion (only store objects)
                foreach ($currentVars as $varName => $value) {
                    if (is_object($value)) {
                        $this->variableValues[$varName] = $value;
                    }
                }

                // Display result if not null
                if ($result !== null) {
                    var_export($result);
                    echo "\n";
                }
            } catch (\ParseError $e) {
                echo "Parse error: " . $e->getMessage() . "\n";
            } catch (\Throwable $e) {
                echo get_class($e) . ": " . $e->getMessage() . "\n";
                echo "  in " . $e->getFile() . " on line " . $e->getLine() . "\n";
            }

            // Clear buffer
            $buffer = '';
        }

        return 0;
    }

    /**
     * Get all available PHP built-in functions
     * @return array<array-key, string>
     */
    protected function getPhpFunctions(): array
    {
        $functions = get_defined_functions();
        return array_merge($functions['internal'] ?? [], $functions['user'] ?? []);
    }

    /**
     * Get PHP language keywords and constructs
     * @return array<array-key, string>
     */
    protected function getPhpKeywords(): array
    {
        return [
            // Control structures
            'if', 'else', 'elseif', 'endif',
            'while', 'endwhile', 'do',
            'for', 'endfor', 'foreach', 'endforeach',
            'switch', 'endswitch', 'case', 'default', 'break', 'continue',
            'return', 'goto',

            // Function and class related
            'function', 'fn',
            'class', 'interface', 'trait', 'enum',
            'extends', 'implements',
            'new', 'clone',
            'public', 'protected', 'private',
            'static', 'final', 'abstract', 'readonly',
            'const', 'var',

            // Object-oriented
            'self', 'parent', 'static',
            'instanceof',

            // Exception handling
            'try', 'catch', 'finally', 'throw',

            // Include/require
            'require', 'require_once', 'include', 'include_once',

            // Other language constructs
            'echo', 'print', 'die', 'exit',
            'isset', 'unset', 'empty',
            'eval', 'list', 'array',
            'declare', 'enddeclare',
            'namespace', 'use', 'as',
            'yield', 'yield from',
            'match',

            // Type declarations
            'int', 'float', 'string', 'bool', 'array', 'object',
            'callable', 'iterable', 'void', 'never', 'mixed',
            'true', 'false', 'null',

            // Other
            'and', 'or', 'xor',
            'global', 'insteadof',
        ];
    }

    /**
     * Get user-defined variables from the session
     * @return array<string, mixed>
     */
    protected function getUserVariables(): array
    {
        return $this->userVariables;
    }

    /**
     * Get all loaded classes, interfaces, and traits
     * Includes both PHP built-in and user-defined classes
     * @return array<array-key, string>
     */
    protected function getLoadedClasses(): array
    {
        $allClasses = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits()
        );

        // Try to get classes from composer autoloader
        $composerClasses = $this->getComposerClasses();
        if (!empty($composerClasses)) {
            $allClasses = array_merge($allClasses, $composerClasses);
        }

        // Return all classes - they will be filtered by prefix match in getCompletions
        return array_unique($allClasses);
    }

    /**
     * Get class names from Composer's autoloader
     */
    protected function getComposerClasses(): array
    {
        $classes = [];

        // Get composer autoloader
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0])) {
                $loader = $autoloader[0];

                // Check if it's a Composer ClassLoader
                if ($loader instanceof \Composer\Autoload\ClassLoader) {
                    // Get PSR-4 prefixes
                    $prefixes = $loader->getPrefixesPsr4();
                    foreach ($prefixes as $namespace => $paths) {
                        foreach ($paths as $path) {
                            $classes = array_merge($classes, $this->scanDirectoryForClasses($path, $namespace));
                        }
                    }

                    // Get PSR-0 prefixes
                    $prefixes = $loader->getPrefixes();
                    foreach ($prefixes as $namespace => $paths) {
                        foreach ($paths as $path) {
                            $classes = array_merge($classes, $this->scanDirectoryForClasses($path, $namespace));
                        }
                    }

                    // Get classmap
                    $classMap = $loader->getClassMap();
                    $classes = array_merge($classes, array_keys($classMap));
                }
            }
        }

        return array_unique($classes);
    }

    /**
     * Scan directory for PHP class files and extract class names
     */
    protected function scanDirectoryForClasses(string $path, string $namespace): array
    {
        $classes = [];

        if (!is_dir($path)) {
            return $classes;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    // Extract relative path from base path
                    $relativePath = substr($file->getPathname(), strlen($path) + 1);
                    // Remove .php extension
                    $relativePath = substr($relativePath, 0, -4);
                    // Convert path separators to namespace separators
                    $className = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
                    // Combine with namespace
                    $fullClassName = rtrim($namespace, '\\') . '\\' . $className;
                    $classes[] = $fullClassName;
                }
            }
        } catch (\Exception $e) {
            // Ignore errors scanning directories
        }

        return $classes;
    }

    /**
     * Extract variables from code buffer
     */
    protected function extractVariablesFromCode(string $code): void
    {
        // Match variable assignments: $varname = ...
        if (preg_match_all('/\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*=/', $code, $matches)) {
            foreach ($matches[1] as $varName) {
                $this->userVariables['$' . $varName] = true;
            }
        }

        // Match foreach variables: foreach ($arr as $key => $value)
        if (preg_match_all('/foreach\s*\([^)]*\s+as\s+(?:\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*=>\s*)?\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches)) {
            foreach ($matches[1] as $varName) {
                if (!empty($varName)) {
                    $this->userVariables['$' . $varName] = true;
                }
            }
            foreach ($matches[2] as $varName) {
                $this->userVariables['$' . $varName] = true;
            }
        }

        // Match function parameters: function foo($param1, $param2)
        if (preg_match_all('/function\s+[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\s*\([^)]*\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches)) {
            foreach ($matches[1] as $varName) {
                $this->userVariables['$' . $varName] = true;
            }
        }

        // Also track variables that are used (not just assigned)
        // This catches cases like: $result = eval($code) where $code and $result are both variables
        if (preg_match_all('/\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches)) {
            foreach ($matches[1] as $varName) {
                $this->userVariables['$' . $varName] = true;
            }
        }
    }

    /**
     * Get method/property completions for an object variable
     * @return array<array-key, string>
     */
    protected function getObjectCompletions(string $varName, string $prefix = ''): array
    {
        // Remove $ prefix if present
        $cleanVarName = ltrim($varName, '$');

        // Check if we have the variable value stored
        if (!isset($this->variableValues[$cleanVarName])) {
            return [];
        }

        $object = $this->variableValues[$cleanVarName];

        // Check if it's actually an object
        if (!is_object($object)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($object);
            $completions = [];

            // Get public methods
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                // Skip magic methods except common ones
                $methodName = $method->getName();
                if (str_starts_with($methodName, '__') &&
                    !in_array($methodName, ['__construct', '__toString', '__invoke'])) {
                    continue;
                }

                $completions[] = $methodName;
            }

            // Get public properties
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            foreach ($properties as $property) {
                $completions[] = $property->getName();
            }

            // Filter by prefix
            if ($prefix !== '') {
                $completions = array_filter($completions, function($item) use ($prefix) {
                    return str_starts_with($item, $prefix);
                });
            }

            // Sort and return unique values
            $completions = array_unique($completions);
            sort($completions);
            return $completions;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get completions based on current input
     * Note: $input is the word being completed (the partial word under cursor)
     * @return array<array-key, string>
     */
    protected function getCompletions(string $input): array
    {
        $completions = [];

        // If input is empty, return all possibilities
        if ($input === '') {
            return [];
        }

        // Determine what type of completion is needed based on input
        if (str_starts_with($input, '$')) {
            // Variable completion
            $completions = array_keys($this->userVariables);

            // Filter by input prefix (case-sensitive for variables)
            $filtered = array_filter($completions, function($item) use ($input) {
                return str_starts_with($item, $input);
            });
        } else {
            // Function, keyword, and class completion
            $completions = array_merge(
                $this->getPhpFunctions(),
                $this->getPhpKeywords(),
                $this->getLoadedClasses()
            );

            // Filter by input prefix (case-insensitive for functions/classes)
            $inputLower = strtolower($input);
            $filtered = array_filter($completions, function($item) use ($inputLower) {
                return str_starts_with(strtolower($item), $inputLower);
            });
        }

        // Return unique sorted results
        $unique = array_unique($filtered);
        sort($unique);
        return $unique;
    }
}
