<?php

namespace Tests\Fixture;

/**
 * A class `new` can build, for the launcher without a container.
 */
class LauncherPlainCommands
{
    /**
     * Say hello.
     */
    public function greet(string $name = 'world'): void
    {
        echo "hello $name\n";
    }
}
