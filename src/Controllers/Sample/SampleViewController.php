<?php

namespace App\Controllers\Sample;

use App\system\Controller;
use App\system\Response;

class SampleViewController extends Controller {
    public function index(): Response {
        $var = ['title' => 'Sample', 'content' => 'Sample Content'];
        return $this->view('sample/index', $var);
    }
}
