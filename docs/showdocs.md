---
sidebar_position: 6
---

# Show method documentation

To show the documentation for a PHP method, use the `--showdocs` option:

```bash
scriptify run \
    "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --showdocs
```

## Example

```bash
$ scriptify run \
    "\\ByJG\\Scriptify\\Sample\\TryMe::ping" \
    --showdocs
```

This will return the following output:

```plaintext
This will return a pong message with the arguments passed and write to the file /tmp/tryme_test.txt
@param string $arg1
@param string|null $arg2

Usage:
scriptify run ByJG/Scriptify/Sample/TryMe::ping --arg <arg1> --arg [arg2]
```

The `--showdocs` option reads the PHPDoc comments from your method and displays:
- Method description
- Parameter information (types, names, whether they're optional)
- Usage examples with the correct syntax. The class is printed with forward slashes, so the line
  can be pasted into a shell without quoting (see [script](script.md))

## Custom usage line

`Runner::showDocs()` accepts an optional usage prefix, for a launcher that wraps
Scriptify behind its own command names:

```php
$runner = new \ByJG\Scriptify\Runner('Some/Name/Space/Maintenance::cleanOldRecords', [], false, $container);
$runner->showDocs('./app user:cleanup');
```

The usage line is then the prefix followed by the parameters as positional values,
without `--arg`:

```plaintext
Delete records older than the given number of days.
@param int $days
@param string|null $table

Usage:
./app user:cleanup <days> [table]
```

Without the argument the output is unchanged.
