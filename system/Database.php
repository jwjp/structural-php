<?php

namespace App\system;

use PDO;

abstract class Database {
    /**
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * 기본 설정 값
     * 필요에 따라서 환경변수 지정 후 protected 변수로 사용
     */
    public function __construct() {
        $ini = new Ini();
        $database = $ini->get('DATABASE');
        $this->pdo = new PDO(
            "mysql:host={$database['HOST']};dbname={$database['DBNAME']}",
            $database['USERNAME'],
            $database['PASSWORD']
        );

        // PDO 기능이 아닌 데이터베이스 자체의 Prepared Statement 사용
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // 에러가 발생하는 경우 Exception 던지도록 설정
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @return QueryBuilder 쿼리 빌더
     */
    public function queryBuilder(): QueryBuilder {
        return new QueryBuilder($this->pdo);
    }

    // TODO: 쿼리 실행문 정리 및 Logger 활용
}