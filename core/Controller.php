<?php

namespace App\core;

use JetBrains\PhpStorm\NoReturn;

abstract class Controller {
    /**
     * @var array|false $ini
     */
    protected array|false $ini;

    /**
     * GET 메소드의 Query-String
     * POST, PATCH, PUT, DELETE 메소드의 Body-Content
     *
     * @var array
     */
    protected array $var;

    /**
     * 요청 URL에서 {} 값을 리소스에서 찾은 값
     * e.g. 요청 URL : /sports/soccer/player/7
     *      리소스 :   /sports/{sportsId}/player/{playerId}
     *      > ['sportsId' => 'soccer', 'playerId' => '7']
     *
     * @var array
     */
    protected array $param;

    /**
     * @param array|false $ini .ini 환경변수 값
     * @param array $var POST, PATCH, PUT, DELETE 메소드의 Body-Content
     * @param array $param 요청 URL에서 {} 값을 리소스에서 찾은 값
     */
    public function __construct(array|false $ini = false, array $var = [], array $param = []) {
        $this->ini = $ini;
        $this->var = $var;
        $this->param = $param;
    }

    /**
     * @param string $view 뷰어 파일 이름
     * @param array $var 전달할 변수
     * @param string $header 헤더 파일 이름
     * @param string $footer 푸터 파일 이름
     * @return void
     */
    #[NoReturn] protected function view(string $view, array $var = [], string $header = 'defaultHeader', string $footer = 'defaultFooter'): void {
        // 전달할 값을 global 변수로 등록
        foreach ($var as $key => $value) {
            $GLOBALS[$key] = $value;
        }

        // View 파일 생성
        include_once $_SERVER['DOCUMENT_ROOT'] . '/template/' . $header . '.php';
        include_once $_SERVER['DOCUMENT_ROOT'] . '/' . $view . '.php';
        include_once $_SERVER['DOCUMENT_ROOT'] . '/template/' . $footer . '.php';
        exit;
    }
}