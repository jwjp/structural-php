<?php

namespace App\system;

class Ini {
    /**
     * .ini 환경변수 값
     *
     * @var array|false $ini
     */
    private array|false $ini;

    public function __construct() {
        $this->ini = parse_ini_file(__DIR__ . '/../.ini', true);
    }

    public function get(string $key): array {
        return $this->ini[$key];
    }

    public function getAll(): array {
        return $this->ini;
    }
}