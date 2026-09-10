<?php

use App\system\QueryBuilder;

function testPdo(): PDO {
    return new class extends PDO {
        public function __construct() {
        }
    };
}

return [
    'builds deterministic parameterized select queries' => static function (): void {
        $debug = (new QueryBuilder(testPdo()))
            ->table('foo f')
            ->select('f.id', 'COUNT(*) AS total')
            ->join('bar b', 'LEFT', 'b.foo_id', '=', 'f.id')
            ->where('f.id', '=', 7)
            ->where('f.deleted_at', 'IS', null)
            ->groupBy('f.id')
            ->having('COUNT(*)', '>', 0)
            ->orderBy('f.id', 'DESC')
            ->limit(10)
            ->offset(5)
            ->debug();

        testSame(
            'SELECT f.id, COUNT(*) AS total FROM foo f LEFT JOIN bar b ON b.foo_id = f.id WHERE f.id = :p1 AND f.deleted_at IS NULL GROUP BY f.id HAVING COUNT(*) > :p2 ORDER BY f.id DESC LIMIT 10 OFFSET 5',
            $debug['query']
        );
        testSame([':p1' => 7, ':p2' => 0], $debug['params']);
    },
    'resets query state when a new table is selected' => static function (): void {
        $qb = new QueryBuilder(testPdo());
        $qb->table('foo')->select('id')->where('id', '=', 1)->debug();
        $debug = $qb->table('bar')->select('id')->debug();
        testSame('SELECT id FROM bar', $debug['query']);
        testSame([], $debug['params']);
    },
    'rejects unsafe identifiers and IS values' => static function (): void {
        testThrows(fn () => (new QueryBuilder(testPdo()))->table('foo; DROP TABLE foo')->select('id')->debug(), InvalidArgumentException::class);
        testThrows(fn () => (new QueryBuilder(testPdo()))->table('foo')->select('id')->where('deleted_at', 'IS', 'NULL')->debug(), InvalidArgumentException::class);
        testThrows(fn () => (new QueryBuilder(testPdo()))->table('foo')->select('id')->orderBy('id', 'RAND()'), InvalidArgumentException::class);
    },
    'requires where clauses for writes' => static function (): void {
        testThrows(fn () => (new QueryBuilder(testPdo()))->table('foo')->delete()->debug(), InvalidArgumentException::class);
    },
];
