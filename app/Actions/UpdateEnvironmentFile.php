<?php

namespace App\Actions;

use RuntimeException;

class UpdateEnvironmentFile
{
    /**
     * Set or update a key in the application's .env file.
     */
    public function set(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            throw new RuntimeException('Environment file (.env) does not exist.');
        }

        if (! is_writable($path)) {
            throw new RuntimeException('Environment file (.env) is not writable.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the environment file (.env).');
        }

        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            $contents = preg_replace($pattern, $key.'='.$value, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n".$key.'='.$value."\n";
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the environment file (.env).');
        }
    }
}
