<?php

namespace ByJG\Scriptify;

class ServiceWriter
{
    protected ?string $overridePath = null;

    public function __construct(?string $overridePath = null)
    {
        $this->overridePath = $overridePath;
    }

    /**
     * @param string $path
     * @param string $contents
     * @return string The path written: $path, or its name under the override path.
     * @throws ScriptifyException
     */
    protected function writeFile(string $path, string $contents): string
    {
        if (!is_null($this->overridePath)) {
            $path = $this->overridePath . '/' . basename($path);
        }

        set_error_handler(function ($number, $error) {
            throw new ScriptifyException($error);
        });
        if (is_null($this->overridePath)) {
            if (!file_exists('/etc/scriptify')) {
                mkdir('/etc/scriptify', 0755, true);
            }
        }
        file_put_contents($path, $contents);
        restore_error_handler();

        return $path;
    }

    /**
     * @param string $path
     * @param string $contents
     * @param int|null $chmod
     * @throws ScriptifyException
     */
    public function writeService(string $path, string $contents, ?int $chmod = null): void
    {
        $written = $this->writeFile($path, $contents);

        if (!is_null($chmod)) {
            chmod($written, $chmod);
        }
    }

    /**
     * @param string $path
     * @param array $environment
     * @throws ScriptifyException
     */
    public function writeEnvironment(string $path, array $environment): void
    {
        $contents = "";
        if (!empty($environment)) {
            $contents = "export " . http_build_query($environment, "", "\nexport ");
        }
        $this->writeFile($path, $contents);
    }
}