<?php

namespace App\system;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * A small dependency injection container.
 *
 * Services are created in one of three ways:
 *
 * - instance():  an object you already built, returned as-is.
 * - singleton(): a factory that runs once; the result is shared.
 * - bind():      a factory that runs on every get().
 *
 * Anything else that names a concrete class is built by reading the
 * constructor with Reflection and resolving each class-typed parameter
 * recursively ("autowiring"). See docs/container.md for a walkthrough.
 */
class Container {
    /** @var array<string, array{factory: Closure, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> Shared results of instance() and singleton(). */
    private array $instances = [];

    /** @var list<string> Classes being built right now, used to report cycles. */
    private array $resolving = [];

    public function __construct() {
        $this->instances[self::class] = $this;
    }

    public function instance(string $id, mixed $value): static {
        unset($this->bindings[$id]);
        $this->instances[$id] = $value;
        return $this;
    }

    /** @param callable(Container): mixed $factory */
    public function singleton(string $id, callable $factory): static {
        unset($this->instances[$id]);
        $this->bindings[$id] = ['factory' => Closure::fromCallable($factory), 'shared' => true];
        return $this;
    }

    /** @param callable(Container): mixed $factory */
    public function bind(string $id, callable $factory): static {
        unset($this->instances[$id]);
        $this->bindings[$id] = ['factory' => Closure::fromCallable($factory), 'shared' => false];
        return $this;
    }

    /** Whether get() can return something for this id without guessing. */
    public function has(string $id): bool {
        return array_key_exists($id, $this->instances) || isset($this->bindings[$id]) || $this->isBuildable($id);
    }

    public function get(string $id): mixed {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            $value = $this->guard($id, fn () => ($binding['factory'])($this));
            if ($binding['shared']) {
                $this->instances[$id] = $value;
            }
            return $value;
        }
        return $this->make($id);
    }

    /**
     * Builds a new object of $class, ignoring bindings for $class itself.
     * Named $parameters override constructor arguments, e.g. make(Foo::class, ['limit' => 5]).
     */
    public function make(string $class, array $parameters = []): object {
        if (!class_exists($class)) {
            throw new ContainerException("Cannot resolve '{$class}': no binding and no such class.");
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Cannot resolve '{$class}': it is abstract or an interface. Bind a concrete class to it.");
        }

        return $this->guard($class, function () use ($reflection, $parameters): object {
            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return $reflection->newInstance();
            }
            return $reflection->newInstanceArgs($this->arguments($constructor, $parameters));
        });
    }

    /**
     * Calls a function or [object, 'method'] and injects its parameters ("method injection").
     * Named $parameters are used first, then class-typed parameters come from the container.
     */
    public function call(callable|array $callable, array $parameters = []): mixed {
        if (is_array($callable)) {
            [$target, $method] = $callable;
            $reflection = new ReflectionMethod($target, $method);
            $object = is_object($target) ? $target : null;
            return $reflection->invokeArgs($object, $this->arguments($reflection, $parameters));
        }
        $closure = Closure::fromCallable($callable);
        return $closure(...$this->arguments(new ReflectionFunction($closure), $parameters));
    }

    /** @return list<mixed> */
    private function arguments(ReflectionFunctionAbstract $function, array $parameters): array {
        $arguments = [];
        foreach ($function->getParameters() as $parameter) {
            $arguments[] = $this->argument($parameter, $parameters);
        }
        return $arguments;
    }

    private function argument(ReflectionParameter $parameter, array $parameters): mixed {
        $name = $parameter->getName();
        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        $type = $parameter->getType();
        $class = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
        if ($class !== null && ($class === 'self' || $class === 'static')) {
            $class = $parameter->getDeclaringClass()?->getName();
        }
        // An explicitly registered service always wins, even for optional parameters.
        if ($class !== null && (array_key_exists($class, $this->instances) || isset($this->bindings[$class]))) {
            return $this->get($class);
        }
        // Optional parameters keep their defaults instead of being guessed.
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($class !== null && $this->isBuildable($class)) {
            return $this->get($class);
        }
        if ($parameter->allowsNull()) {
            return null;
        }

        $owner = $parameter->getDeclaringFunction();
        $where = $owner instanceof ReflectionMethod
            ? $owner->getDeclaringClass()->getName() . '::' . $owner->getName() . '()'
            : $owner->getName() . '()';
        if ($class !== null && (interface_exists($class) || class_exists($class))) {
            throw new ContainerException("Cannot resolve parameter \${$name} of {$where}: "
                . "{$class} is an interface or abstract class. Bind a concrete class to it.");
        }
        throw new ContainerException("Cannot resolve parameter \${$name} of {$where}. "
            . 'Give it a class type, a default value, or pass it by name.');
    }

    private function isBuildable(string $id): bool {
        return class_exists($id) && (new ReflectionClass($id))->isInstantiable();
    }

    /**
     * Tracks the chain of services being built so A -> B -> A fails with a clear message
     * instead of recursing until the stack overflows.
     */
    private function guard(string $id, Closure $build): mixed {
        if (in_array($id, $this->resolving, true)) {
            $cycle = [...array_slice($this->resolving, (int) array_search($id, $this->resolving, true)), $id];
            throw new ContainerException('Circular dependency: ' . implode(' -> ', $cycle));
        }
        $this->resolving[] = $id;
        try {
            return $build();
        } finally {
            array_pop($this->resolving);
        }
    }
}
