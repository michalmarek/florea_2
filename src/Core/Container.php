<?php declare(strict_types=1);

namespace Core;

use Exception;

/**
 * Dependency Injection Container
 *
 * Manages service instantiation and dependency resolution.
 * Supports manual service registration via factory functions.
 *
 * @package Core
 */
class Container
{
    /**
     * Registered service factories
     *
     * Array of callable factories keyed by service identifier.
     * Each factory is responsible for creating and returning a service instance.
     *
     * @var array<string, callable>
     */
    private array $services = [];

    /**
     * Cached service instances
     *
     * Stores already instantiated services to ensure singleton behavior.
     * Services are created only once on first access and reused afterwards.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Register a service factory
     *
     * Registers a factory function that will be used to create the service
     * when requested. The factory receives this Container instance as parameter,
     * allowing it to resolve dependencies.
     *
     * @param string $id Unique service identifier (typically FQCN)
     * @param callable $factory Factory function: function(Container $c): object
     * @return void
     *
     * @example
     * $container->register(Database::class, function(Container $c) {
     *     return new Database('localhost', 'dbname');
     * });
     */
    public function register(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    /**
     * Retrieve a service from the container
     *
     * Returns a cached instance if available, uses registered factory if exists,
     * or attempts autowiring for classes with type-hinted constructors.
     *
     * @param string $id Service identifier
     * @return object The service instance
     * @throws Exception If service cannot be resolved
     */
    public function get(string $id): object
    {
        // Return cached instance if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // If service is registered, use factory
        if (isset($this->services[$id])) {
            $factory = $this->services[$id];
            $instance = $factory($this);
            $this->instances[$id] = $instance;
            return $instance;
        }

        // Attempt autowiring if class exists
        if (class_exists($id)) {
            $instance = $this->autowire($id);
            $this->instances[$id] = $instance;
            return $instance;
        }

        // Service not found
        throw new Exception("Service '{$id}' not found in container.");
    }

    /**
     * Check if service is registered or can be autowired
     *
     * @param string $id Service identifier
     * @return bool True if service exists or can be created
     */
    public function has(string $id): bool
    {
        // Je služba zaregistrovaná?
        if (isset($this->services[$id])) {
            return true;
        }

        // Existuje třída s tímto jménem?
        return class_exists($id);
    }

    /**
     * Automatically instantiate a class with dependency injection
     *
     * Uses reflection to analyze constructor parameters and automatically
     * resolve dependencies from the container.
     *
     * @param string $className Fully qualified class name
     * @return object New instance with all dependencies injected
     * @throws Exception If class doesn't exist or has unresolvable dependencies
     */
    private function autowire(string $className): object
    {
        if (!class_exists($className)) {
            throw new Exception("Class '{$className}' does not exist.");
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        // No constructor - instantiate without parameters
        if ($constructor === null) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Parameter must have class type hint
            if ($type === null || $type->isBuiltin()) {
                throw new Exception(
                    "Cannot autowire parameter '{$parameter->getName()}' in class '{$className}'. " .
                    "Parameter must have a class type hint."
                );
            }

            $typeName = $type->getName();
            $dependencies[] = $this->get($typeName);
        }

        return new $className(...$dependencies);
    }
}
