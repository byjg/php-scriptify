<?php

namespace ByJG\Scriptify;

use ByJG\JinjaPhp\Template;

class Scriptify
{
    protected static ?ServiceWriter $writer = null;

    public static function setWriter(?ServiceWriter $writer): void
    {
        self::$writer = $writer;
    }

    public static function getWriter(): ?ServiceWriter
    {
        if (self::$writer == null) {
            self::$writer = new ServiceWriter();
        }

        return self::$writer;
    }

    /**
     * @throws \Exception
     */
    public static function  install(
        $svcName,
        $className,
        $bootstrap,
        $curdir,
        $template,
        $description,
        $consoleArgs,
        $environment,
        $check = true
    ): bool
    {
        $targetPathAvailable = [
            'initd' => "/etc/init.d/$svcName",
            'crond' => "/etc/cron.d/$svcName",
            'upstart' => "/etc/init/$svcName.conf",
            'systemd' => "/etc/systemd/system/$svcName.service"
        ];
        if (!isset($targetPathAvailable[$template])) {
            throw new \Exception(
                "Template $template does not exists. Available templates are: "
                . implode(',', array_keys($targetPathAvailable))
            );
        }

        $targetServicePath = $targetPathAvailable[$template];
        $templatePath = __DIR__ . "/../template/linux-" . $template . "-service.jinja";

        if (!file_exists($templatePath)) {
            throw new \Exception("Template '$templatePath' not found");
        }

        if (!file_exists(realpath($curdir)) && $check) {
            throw new \Exception("RootPath '" . $curdir . "' not found. Use an absolute path. e.g. /projects/example");
        }

        if (!file_exists($curdir . '/' . $bootstrap) && $check) {
            throw new \Exception("Bootstrap '$bootstrap' not found. Use a relative path from root directory. e.g. vendor/autoload.php");
        }

        $autoload = realpath(__DIR__ . "/../vendor/autoload.php");
        if (!file_exists($autoload)) {
            $autoload = realpath(__DIR__ . "/../../../autoload.php");
            if (!file_exists($autoload) && $check) {
                throw new \Exception('Scriptify autoload not found. Did you run `composer dump-autload`?');
            }
        }

        $consoleArgsPrepared = '';
        if (!empty($consoleArgs)) {
            $consoleArgsPrepared = '--args ' . implode(' --args ', $consoleArgs);
        }

        $environmentPrepared = '/etc/scriptify/' . $svcName . '.env';

        $scriptifyService = realpath(__DIR__ . "/../scripts/scriptify");

        $vars = [
            'description' => $description,
            'daemonbootstrap' => $autoload,
            'class' => str_replace("\\", "\\\\", $className),
            'bootstrap' => $bootstrap,
            'svcname' => $svcName,
            'rootpath' => realpath($curdir),
            'consoleargs' => $consoleArgsPrepared,
            'environment' => $environmentPrepared,
            'phppath' => PHP_BINARY,
            'scriptifyservice' => $scriptifyService,
            'envcmdline' => implode(
                ' ',
                array_map(
                    /**
                     * @param mixed $v
                     * @param string $k
                     * @return string
                     */
                    function ($v, $k) {
                        return "$k=\"$v\"";
                    },
                    $environment,
                    array_keys($environment)
                )
            )
        ];

        $template = new Template(file_get_contents($templatePath));
        $templateStr = $template->render($vars);

        // Check if is OK
        if ($check) {
            require_once($vars['bootstrap']);
            $classParts = explode('::', str_replace("\\\\", "\\", $vars['class']));
            if (!class_exists($classParts[0])) {
                throw new \Exception('Could not find class ' . $classParts[0]);
            }
            $className = $classParts[0];
            $classTest = new $className();
            if (!method_exists($classTest, $classParts[1])) {
                throw new \Exception('Could not find method ' . $vars['class']);
            }
        }

        Scriptify::getWriter()->writeEnvironment($environmentPrepared, $environment);
        Scriptify::getWriter()->writeService($targetServicePath, $templateStr, $template == 'initd' ? 0755 : null);

        return true;
    }

    /**
     * @throws ScriptifyException
     */
    public static function uninstall(string $svcName): void
    {
        $list = [
            "/etc/init.d/$svcName",
            "/etc/init/$svcName.conf",
            "/etc/systemd/system/$svcName.service",
            '/etc/scriptify/' . $svcName . '.env',
            '/etc/cron.d/' . $svcName,
        ];

        $found = false;
        foreach ($list as $service) {
            if (file_exists($service)) {
                $found = true;
                if (strpos($service, ".env") === false && !self::isScriptifyService($service)) {
                    throw new ScriptifyException("Service '$svcName' was not created by Scriptify");
                }
                unlink($service);
            }
        }

        if (!$found) {
            throw new ScriptifyException("Service '$svcName' does not exists");
        }

        restore_error_handler();
    }

    protected static function isScriptifyService(string $filename): bool
    {
        set_error_handler(function ($number, $error) {
            throw new \Exception($error);
        });

        if (!file_exists($filename)) {
            return false;
        }
        $contents = file_get_contents($filename);

        return (str_contains($contents, 'PHP_SCRIPTIFY'));
    }

    public static function listServices(): array
    {
        $list = [
            "initd" => glob("/etc/init.d/*"),
            "crond" => glob("/etc/cron.d/*"),
            "upstart" => glob("/etc/init/*.conf"),
            "systemd" => glob("/etc/systemd/system/*.service")
        ];
        $return = [];

        foreach ($list as $svcType => $filenames) {
            foreach ($filenames as $filename) {
                if (self::isScriptifyService($filename)) {
                    $return[] = $svcType . ": " . 
                        str_replace(
                        '.service',
                        '',
                        str_replace(
                            '.conf',
                            '',
                            basename($filename)
                        )
                    );
                }
            }
        }

        return $return;
    }

}
