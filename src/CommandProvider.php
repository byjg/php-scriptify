<?php

namespace ByJG\Scriptify;

/**
 * The commands of an application, for Launcher.
 *
 * A class, not a file returning an array: the targets are real class references
 * (`::class`), so a typo fails in the IDE and in static analysis, and a rename is
 * refactor-safe.
 *
 * ```php
 * final class AppCommands implements CommandProvider
 * {
 *     public function commands(): array
 *     {
 *         return [
 *             'payment' => PaymentCommands::class,                 // payment:charge, payment:refund, ...
 *             'cache:clear' => CacheService::class . '::clear',   // one command
 *         ];
 *     }
 * }
 * ```
 */
interface CommandProvider
{
    /**
     * A group name => a class, whose public methods each become `<group>:<kebab-method>`;
     * or a command name => "Class::method".
     *
     * @return array<string, string>
     */
    public function commands(): array;
}
