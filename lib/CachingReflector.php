<?php

namespace Auryn;

class CachingReflector implements Reflector
{
    public const CACHE_KEY_CLASSES = 'auryn.refls.classes.';
    public const CACHE_KEY_CTORS = 'auryn.refls.ctors.';
    public const CACHE_KEY_CTOR_PARAMS = 'auryn.refls.ctor-params.';
    public const CACHE_KEY_FUNCS = 'auryn.refls.funcs.';
    public const CACHE_KEY_METHODS = 'auryn.refls.methods.';

    private $reflector;
    private $cache;

    public function __construct(?Reflector $reflector = null, ?ReflectionCache $cache = null)
    {
        $this->reflector = $reflector ?: new StandardReflector();
        $this->cache = $cache ?: new ReflectionCacheArray();
    }

    public function getClass($class)
    {
        $cacheKey = self::CACHE_KEY_CLASSES . strtolower($class);

        if (($reflectionClass = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectionClass = $this->reflector->getClass($class));
        }

        return $reflectionClass;
    }

    public function getCtor($class)
    {
        $cacheKey = self::CACHE_KEY_CTORS . strtolower($class);

        if (($reflectedCtor = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedCtor = $this->reflector->getCtor($class));
        }

        return $reflectedCtor;
    }

    public function getCtorParams($class)
    {
        $cacheKey = self::CACHE_KEY_CTOR_PARAMS . strtolower($class);

        if (($reflectedCtorParams = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedCtorParams = $this->reflector->getCtorParams($class));
        }

        return $reflectedCtorParams;
    }

    public function getParamTypeHint(\ReflectionFunctionAbstract $function, \ReflectionParameter $param)
    {
        $lowParam = strtolower($param->name);

        if ($function instanceof \ReflectionMethod) {
            $lowClass = strtolower($function->class);
            $lowMethod = strtolower($function->name);
            $paramCacheKey = self::CACHE_KEY_CLASSES . "{$lowClass}.{$lowMethod}.param-{$lowParam}";
        } else {
            $lowFunc = strtolower($function->name);
            $paramCacheKey = (strpos($lowFunc, '{closure}') === false)
                ? self::CACHE_KEY_FUNCS . ".{$lowFunc}.param-{$lowParam}"
                : null;
        }

        $typeHint = ($paramCacheKey === null) ? false : $this->cache->fetch($paramCacheKey);

        if (false === $typeHint) {
            $typeHint = $this->reflector->getParamTypeHint($function, $param);
            if ($paramCacheKey !== null) {
                $this->cache->store($paramCacheKey, $typeHint);
            }
        }

        return $typeHint;
    }

    public function getFunction($functionName)
    {
        $lowFunc = strtolower($functionName);
        $cacheKey = self::CACHE_KEY_FUNCS . $lowFunc;

        if (($reflectedFunc = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedFunc = $this->reflector->getFunction($functionName));
        }

        return $reflectedFunc;
    }

    public function getMethod($classNameOrInstance, $methodName)
    {
        $className = is_string($classNameOrInstance)
            ? $classNameOrInstance
            : get_class($classNameOrInstance);

        $cacheKey = self::CACHE_KEY_METHODS . strtolower($className) . '.' . strtolower($methodName);

        if (($reflectedMethod = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedMethod = $this->reflector->getMethod($classNameOrInstance, $methodName));
        }

        return $reflectedMethod;
    }
}
