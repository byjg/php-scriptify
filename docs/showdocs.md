---
sidebar_position: 5
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
scriptify run "\\ByJG\\Scriptify\\Sample\\TryMe::ping" --arg <arg1> --arg [arg2]
```

The `--showdocs` option reads the PHPDoc comments from your method and displays:
- Method description
- Parameter information (types, names, whether they're optional)
- Usage examples with the correct syntax
