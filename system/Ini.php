<?php

namespace App\system;

use RuntimeException;

class Ini {
    private array $ini = [];

    public function __construct(?string $path = null) {
        $required = $path !== null;
        $path ??= dirname(__DIR__) . '/.ini';
        if (!is_file($path)) {
            if ($required) {
                throw new RuntimeException('The configuration file does not exist.');
            }
            return;
        }

        // Literal parsing preserves passwords such as "false" and "null".
        $parsed = @parse_ini_file($path, true, INI_SCANNER_RAW);
        if ($parsed === false) {
            throw new RuntimeException('The configuration file could not be parsed.');
        }
        $this->ini = $parsed;
    }

    public function get(string $key): array {
        if (!isset($this->ini[$key]) || !is_array($this->ini[$key])) {
            throw new RuntimeException("The configuration section {$key} is missing.");
        }
        return $this->ini[$key];
    }

    public function getAll(): array {
        return $this->ini;
    }
}
