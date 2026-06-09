<?php

namespace Core;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Exception;

/**
 * Dependency Injection Container
 */
class Container
{
    /**
     * The container's shared instances (singletons).
     *
     * @var array
     */
    protected array $instances = [];

    /**
     * The container's bindings.
     *
     * @var array
     */
    protected array $bindings = [];

    /**
     * Register a binding in the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @param bool $shared
     * @return void
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        // If no concrete is given, we assume the abstract is the concrete
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Register a shared (singleton) binding.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as shared.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @return mixed
     * @throws Exception
     */
    public function get(string $abstract)
    {
        // If the instance is already resolved as a singleton, return it
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get the concrete type or closure
        $concrete = $abstract;
        $shared = false;

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract]['concrete'];
            $shared = $this->bindings[$abstract]['shared'];
        }

        // If the concrete is a Closure, execute it
        if ($concrete instanceof \Closure) {
            $object = $concrete($this);
        } else {
            // Otherwise, we autowire it via Reflection
            $object = $this->build($concrete);
        }

        // If it should be shared, cache it
        if ($shared) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Build an instance of the class (autowiring).
     *
     * @param string $concrete
     * @return mixed
     * @throws Exception
     */
    protected function build(string $concrete)
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new Exception("Target class [$concrete] does not exist.", 0, $e);
        }

        // Check if the class is instantiable (e.g. not an interface or abstract class)
        if (!$reflector->isInstantiable()) {
            throw new Exception("Target class [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        // If there is no constructor, we can just instantiate it
        if ($constructor === null) {
            return new $concrete;
        }

        // Get the constructor's parameters
        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve all constructor parameters.
     *
     * @param ReflectionParameter[] $parameters
     * @return array
     * @throws Exception
     */
    protected function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            // Get the parameter type (ReflectionType)
            $type = $parameter->getType();

            // If the parameter has a class type, resolve it from the container
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } else {
                // Check if the parameter name is bound as a service key
                if ($this->has($parameter->getName())) {
                    $dependencies[] = $this->get($parameter->getName());
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Unresolvable dependency [{$parameter->getName()}] in class {$parameter->getDeclaringClass()->getName()}");
                }
            }
        }

        return $dependencies;
    }
}
