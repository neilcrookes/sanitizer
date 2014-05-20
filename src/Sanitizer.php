<?php

namespace Rees\Sanitizer;

use Closure;
use Illuminate\Container\Container;

class Sanitizer
{
    /**
     * Array of registered sanitizers.
     *
     * @var array
     */
    protected $sanitizers = array();

    /**
     * Container instance used to resolve classes.
     *
     * @var Illuminate\Container\Container
     */
    protected $container;

    /**
     * Allow a container instance to be set via constructor.
     *
     * @param  mixed $container
     */
    public function __construct($container = null)
    {
        // If the container isn't provided...
        if (!$container instanceof Container) {

            // ... use an instance of the illuminate container.
            $container = new Container;
        }

        // Set the container property.
        $this->container = $container;
    }

    /**
     * Register a new sanitization method.
     *
     * @param  string $name
     * @param  mixed  $callback
     * @return void
     */
    public function register($name, $callback)
    {
        // Add the sanitizer to the set.
        $this->sanitizers[$name] = $callback;
    }

    /**
     * Sanitize a dataset using rules.
     *
     * @param  array $rules
     * @param  array $data
     * @return void
     */
    public function sanitize($rules, &$data)
    {
        // Iterate rules to be applied.
        foreach ($rules as $field => $ruleset) {

            // Execute sanitizers over a specific field.
            $this->sanitizeField($data, $field, $ruleset);
        }
    }

    /**
     * Execute sanitization over a specific field.
     *
     * @param  array  $data
     * @param  string $field
     * @param  mixed  $ruleset
     * @return
     */
    protected function sanitizeField(&$data, $field, $ruleset)
    {
        // If we have a piped ruleset, explode it.
        if (is_string($ruleset)) {
            $ruleset = explode('|', $ruleset);
        }

        // Assign field by reference from data array.
        $field = &$data[$field];

        // Iterate the rule set.
        foreach ($ruleset as $rule) {

            // Get the sanitizer.
            if(!$sanitizer = $this->getSanitizer($rule)) {
                continue;
            }

            // Execute the sanitizer to mutate the field.
            $field = $this->executeSanitizer($sanitizer, $field);
        }
    }

    /**
     * Retrieve a sanitizer by key.
     *
     * @param  string $key
     * @return Callable
     */
    protected function getSanitizer($key)
    {
        return array_get($this->sanitizers, $key, $key);
    }

    /**
     * Execute a sanitizer using the appropriate method.
     *
     * @param  mixed $sanitizer
     * @param  mixed $value
     * @return mixed
     */
    public function executeSanitizer($sanitizer, $value)
    {
        // If the sanitizer is a callback...
        if (is_callable($sanitizer)) {

            // ...execute the sanitizer and return the mutated value.
            return call_user_func($sanitizer, $value);
        }

        // If the sanitizer is a Closure...
        if ($sanitizer instanceof Closure) {

            // ...execute the Closure and return mutated value.
            return $sanitizer($value);
        }

        // Transform a container resolution to a callback.
        $sanitizer = $this->resolveCallback($sanitizer);

        // If the sanitizer is a ...
        if (is_callable($sanitizer)) {

            // ...execute the sanitizer and return the mutated value.
            return call_user_func($sanitizer, $value);
        }

        // If the sanitizer can't be called, return the passed value.
        return $value;
    }

    /**
     * Resolve a callback from a class and method pair.
     *
     * @param  string $callback
     * @return array
     */
    protected function resolveCallback($callback)
    {
        // Explode by method separater.
        $segments = explode('@', $callback);

        // Set default method if required.
        $method = count($segments) == 2 ? $segments[1] : 'sanitize';

        // Return the constructed callback.
        return array($this->container->make($segments[0]), $method);
    }
}