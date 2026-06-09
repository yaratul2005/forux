<?php

namespace Core;

/**
 * Hook and Event Dispatcher (Observer Pattern)
 */
class Hook
{
    /**
     * Registered actions.
     *
     * @var array
     */
    protected array $actions = [];

    /**
     * Registered filters.
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * Register an action callback.
     *
     * @param string $event
     * @param callable $callback
     * @param int $priority Lower numbers execute first. Default 10.
     * @return void
     */
    public function addAction(string $event, callable $callback, int $priority = 10): void
    {
        $this->actions[$event][$priority][] = $callback;
    }

    /**
     * Fire an action event.
     *
     * @param string $event
     * @param mixed ...$args
     * @return void
     */
    public function doAction(string $event, ...$args): void
    {
        if (!isset($this->actions[$event])) {
            return;
        }

        // Sort by priority (keys)
        $priorities = $this->actions[$event];
        ksort($priorities);

        foreach ($priorities as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Register a filter callback.
     *
     * @param string $event
     * @param callable $callback
     * @param int $priority Lower numbers execute first. Default 10.
     * @return void
     */
    public function addFilter(string $event, callable $callback, int $priority = 10): void
    {
        $this->filters[$event][$priority][] = $callback;
    }

    /**
     * Apply filters to a value.
     *
     * @param string $event
     * @param mixed $value The initial value to be filtered.
     * @param mixed ...$args Additional arguments passed to the filters.
     * @return mixed The filtered value.
     */
    public function applyFilters(string $event, $value, ...$args)
    {
        if (!isset($this->filters[$event])) {
            return $value;
        }

        // Sort by priority (keys)
        $priorities = $this->filters[$event];
        ksort($priorities);

        foreach ($priorities as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                // Pass the current value as the first parameter
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }
}
