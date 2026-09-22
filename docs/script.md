---
sidebar_position: 1
---

# Call a PHP method from the command line

Assuming you have a class and a method like this:

```php
<?php
namespace Some\Name\Space;

class MyExistingClass
{
	// ...

    /**
     * This is a sample method 
     * @param string $param1
     * @param string $param2
     */
    public function someExistingMethod(string $param1, ?string $param2 = null)
    {
        // Your code
    }

	// ...
}
```

You can run this method from the command line with the following command:

```bash
scriptify run \
    "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --arg value1 \
    --arg value2
```

## Naming the class without fighting the shell

A backslash is the shell's escape character, which is why the form above is quoted and
doubled. Scriptify also accepts **forward slashes** as namespace separators, and they
need no escaping at all — anywhere:

```bash
scriptify run Some/Name/Space/MyExistingClass::someExistingMethod \
    --arg value1 \
    --arg value2
```

The two spell the same class. A forward slash is not valid in a PHP identifier or
namespace, so there is nothing for it to be confused with, and Scriptify converts it
before anything else happens — a service installed this way still records the
canonical backslash form.

This is worth knowing wherever the command passes through another layer of quoting. In
a `composer.json` script, for example, each backslash is escaped once by JSON and again
by the shell, so a single separator becomes four characters:

```json
"revalidate": "vendor/bin/scriptify run \"\\\\Some\\\\Name\\\\Space\\\\MyClass::method\""
```

against:

```json
"revalidate": "vendor/bin/scriptify run Some/Name/Space/MyClass::method"
```

If you prefer backslashes, use **single quotes** in the shell: they keep the backslash
literal, so one per separator is enough.

```bash
scriptify run '\Some\Name\Space\MyExistingClass::someExistingMethod'
```

If is necessary to execute this method in a specific environment you can use the `--bootstrap` and `--rootdir` parameters:

```bash
scriptify run \
    "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --bootstrap "relative/path/to/bootstrap.php" \
    --rootdir "/path/to/root" \
    --arg value1 \
    --arg value2
```
