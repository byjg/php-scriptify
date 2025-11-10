---
sidebar_position: 4
---

# Interactive PHP Terminal

The `terminal` command opens an interactive PHP shell (REPL) with your project's autoloader and environment variables preloaded.

## Basic Usage

Start an interactive terminal:

```bash
scriptify terminal
```

This will:
- Discover and load your project's autoloader (`vendor/autoload.php`)
- Start an interactive PHP prompt
- Allow you to execute PHP code interactively

## Example Session

```bash
$ scriptify terminal
Starting interactive PHP terminal...
Autoloader: /path/to/vendor/autoload.php

Scriptify Interactive Terminal
Type 'exit' or press Ctrl+D to quit

php> $x = 5
php> $x * 2
10
php> function hello($name) {
php*   return "Hello, $name!";
php* }
php> hello("World")
'Hello, World!'
php> exit
```

## Loading Environment Variables

### From a Service

If you have an installed service, you can load its environment variables:

```bash
scriptify terminal myservice
```

This loads environment variables from `/etc/scriptify/myservice.env`.

### From Command Line

Pass environment variables directly:

```bash
scriptify terminal --env DEBUG=true --env LOG_LEVEL=verbose
```

### Combining Both

You can combine both approaches (command line options override service values):

```bash
scriptify terminal myservice --env EXTRA_VAR=value
```

## Preloading Commands

You can preload commands from a file to automatically set up your terminal environment:

```bash
scriptify terminal --preload .scriptify-preload.php
# or using the short option
scriptify terminal -p .scriptify-preload.php
```

### Example Preload File

Create a `.scriptify-preload.php` file:

```php
<?php
// Define commonly used namespace imports
use DateTime;
use DateTimeImmutable;
use App\Models\User;
use App\Services\MyService;

// Define helper variables
$today = new DateTime();

// Define helper functions
function dd($var) {
    var_dump($var);
    die();
}
```

Now when you start the terminal with `--preload`, all these imports, variables, and functions will be available immediately.

## Features

- **Interactive Prompt**: `php>` for single-line input, `php*` for multi-line continuation
- **Command History**: Use up/down arrows to recall previous commands
- **Multi-line Support**: Automatically detects unclosed braces, brackets, and parentheses
- **Auto-return Expressions**: Typing `2 + 2` automatically displays the result
- **Error Handling**: Parse errors and exceptions don't crash the session
- **Exit Commands**: Type `exit`, `quit`, or press Ctrl+D to quit
- **Namespace Support**: Use `use` statements to import classes, and they persist across commands
- **Preload Files**: Load common imports and helper functions from a file

## Autoloader Discovery

The terminal command discovers the autoloader in this order:

1. `SCRIPTIFY_BOOTLOADER` environment variable
2. Current directory's `vendor/autoload.php`
3. Scriptify's own autoloader locations

## Requirements

The `terminal` command requires the PHP `readline` extension. This is commonly available in most PHP installations.

If the `readline` extension is not available, you'll see an error message with installation instructions.

## Use Cases

The interactive terminal is useful for:

- Testing PHP code snippets quickly
- Exploring your project's classes and methods
- Debugging with access to your full environment
- Prototyping new features
- Running quick database queries or API calls
