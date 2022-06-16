<?php

namespace Framework\Core;

class injection
{
    private static array $classes = [];

    /**
     * Returns a class as an object
     * @param string $className
     */
    public static function getClass(string $className)
    {
        if (isset(self::$classes[$className])) {
            return self::$classes[$className];
        }
        return self::createClass($className);
    }

    static function createClass($class)
    {
        $span = opentelemetry::startSpan("injection.loadclass.$class",true,$scope);
        self::$classes[$class] = new $class;
        $scope->detach();
        $span->end();
        return self::$classes[$class];
    }
}