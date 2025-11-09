<?php

namespace ByJG\Scriptify\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TerminalCommand extends Command
{
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
            return realpath($autoload);
        }

        // Try default locations
        $defaultPaths = [
            getcwd() . '/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../../../../autoload.php'
        ];

        foreach ($defaultPaths as $path) {
            if (file_exists($path)) {
                return realpath($path);
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

        // Enable readline history
        readline_completion_function(function() {
            // Return empty array - can be enhanced later with context-aware completions
            return [];
        });

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
            if ($lastChar !== ';' && $lastChar !== '}') {
                $code = "return ($code);";
            }

            // Execute the code
            try {
                $result = eval($code);

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
}
