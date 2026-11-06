<?php

use App\system\Container;
use App\system\ContainerException;

interface ContainerTestClock {
    public function now(): string;
}

class ContainerTestFixedClock implements ContainerTestClock {
    public function now(): string {
        return '2026-01-01';
    }
}

class ContainerTestLogger {
    public array $lines = [];
}

class ContainerTestReport {
    public function __construct(
        public ContainerTestClock $clock,
        public ContainerTestLogger $logger,
        public int $limit = 10,
        public ?ContainerTestLogger $optional = null
    ) {
    }
}

class ContainerTestNeedsScalar {
    public function __construct(public string $name) {
    }
}

class ContainerTestCycleA {
    public function __construct(public ContainerTestCycleB $b) {
    }
}

class ContainerTestCycleB {
    public function __construct(public ContainerTestCycleA $a) {
    }
}

return [
    'autowires constructor dependencies and keeps defaults' => static function (): void {
        $container = (new Container())->singleton(ContainerTestClock::class, fn () => new ContainerTestFixedClock());
        $report = $container->get(ContainerTestReport::class);
        testSame('2026-01-01', $report->clock->now());
        testTrue($report->logger instanceof ContainerTestLogger);
        testSame(10, $report->limit);
        testSame(null, $report->optional, 'Unregistered optional dependencies keep their default.');
    },
    'registered services are injected even into optional parameters' => static function (): void {
        $logger = new ContainerTestLogger();
        $container = (new Container())
            ->singleton(ContainerTestClock::class, fn () => new ContainerTestFixedClock())
            ->instance(ContainerTestLogger::class, $logger);
        $report = $container->get(ContainerTestReport::class);
        testSame($logger, $report->logger);
        testSame($logger, $report->optional);
    },
    'singletons are shared and bindings are rebuilt' => static function (): void {
        $container = (new Container())
            ->singleton('shared', fn () => new ContainerTestLogger())
            ->bind('fresh', fn () => new ContainerTestLogger());
        testSame($container->get('shared'), $container->get('shared'));
        testTrue($container->get('fresh') !== $container->get('fresh'));
        testSame($container, $container->get(Container::class), 'The container can inject itself.');
    },
    'make builds new objects and accepts named overrides' => static function (): void {
        $container = (new Container())->singleton(ContainerTestClock::class, fn () => new ContainerTestFixedClock());
        $first = $container->make(ContainerTestReport::class, ['limit' => 3]);
        testSame(3, $first->limit);
        testTrue($first !== $container->make(ContainerTestReport::class));
        testSame('Ada', $container->make(ContainerTestNeedsScalar::class, ['name' => 'Ada'])->name);
    },
    'call injects method parameters by name and type' => static function (): void {
        $container = (new Container())->singleton(ContainerTestClock::class, fn () => new ContainerTestFixedClock());
        $result = $container->call(
            static fn (string $id, ContainerTestClock $clock, int $page = 1): string => "{$id}@{$clock->now()}#{$page}",
            ['id' => '7']
        );
        testSame('7@2026-01-01#1', $result);
    },
    'explains interfaces, scalars and cycles it cannot resolve' => static function (): void {
        $container = new Container();
        testThrows(fn () => $container->get(ContainerTestReport::class), ContainerException::class,
            fn ($e) => testTrue(str_contains($e->getMessage(), 'interface')));
        testThrows(fn () => $container->get(ContainerTestNeedsScalar::class), ContainerException::class,
            fn ($e) => testTrue(str_contains($e->getMessage(), '$name of ContainerTestNeedsScalar::__construct()')));
        testThrows(fn () => $container->get(ContainerTestCycleA::class), ContainerException::class,
            fn ($e) => testSame('Circular dependency: ContainerTestCycleA -> ContainerTestCycleB -> ContainerTestCycleA', $e->getMessage()));
        testThrows(fn () => $container->get('missing.service'), ContainerException::class);
        testTrue(!$container->has('missing.service') && $container->has(ContainerTestLogger::class));
    },
];
