<?php

namespace App\Services\Translation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use RuntimeException;

class DistributedFileLoader extends FileLoader
{
    protected $paths = [];

    /**
     * Create a new file loader instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $path
     * @return void
     */
    public function __construct(Filesystem $files, array $paths = [])
    {
        $this->paths = $paths;
        $this->files = $files;
    }

    /**
     * Load a locale from a given path.
     *
     * @param  string  $path
     * @param  string  $locale
     * @param  string  $group
     * @return array
     */
    protected function loadPath($path, $locale, $group)
    {
        $result = [];
        foreach ($this->paths as $path) {
            $result = recuresive_array_merge($result, parent::loadPath($path, $locale, $group));
        }
        return $result;
    }

    protected function loadJsonPaths($locale)
    {
        return collect(array_merge($this->jsonPaths, $this->paths))
            ->reduce(function ($output, $path) use ($locale) {
                if ($path && $this->files->exists($full = "{$path}/{$locale}.json")) {
                    $decoded = json_decode($this->files->get($full), true);

                    if (is_null($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                        throw new RuntimeException("Translation file [{$full}] contains an invalid JSON structure.");
                    }

                    $output = array_merge($output, $decoded);
                }

                return $output;
            }, []);
    }
}
