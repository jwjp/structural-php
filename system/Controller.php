<?php

namespace App\system;

abstract class Controller {
    protected array $ini;
    protected array $var;
    protected array $param;
    protected ?Request $request;

    public function __construct(array $ini = [], array $var = [], array $param = [], ?Request $request = null) {
        $this->ini = $ini;
        $this->var = $var;
        $this->param = $param;
        $this->request = $request;
    }

    protected function view(string $view, array $var = [], string $header = 'defaultHeader', string $footer = 'defaultFooter'): Response {
        $root = dirname(__DIR__) . '/public';
        $files = [
            $root . '/template/' . $this->viewName($header) . '.php',
            $root . '/' . trim($view, '/') . '.php',
            $root . '/template/' . $this->viewName($footer) . '.php',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new HttpException(500, 'View file is missing.');
            }
        }

        ob_start();
        try {
            extract($var, EXTR_SKIP);
            foreach ($files as $file) {
                include $file;
            }
            return Response::html((string) ob_get_clean());
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function viewName(string $name): string {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new HttpException(500, 'View name is invalid.');
        }
        return $name;
    }
}
