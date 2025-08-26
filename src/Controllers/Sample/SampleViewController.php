<?php

namespace App\Controllers\Sample;

use App\system\Controller;
use JetBrains\PhpStorm\NoReturn;

class SampleViewController extends Controller {
    #[NoReturn] public function index(): void {
        $var = ['title' => 'Sample', 'content' => 'Sample Content'];
        $this->view('sample/index', $var);
    }
}