<?php

namespace Auryn;

class Injector
{
    public const A_RAW = ':';
    public const A_DELEGATE = '+';
    public const A_DEFINE = '@';
    public const I_BINDINGS = 1;
    public const I_DELEGATES = 2;
    public const I_PREPARES = 4;
    public const I_ALIASES = 8;
    public const I_SHARES = 16;
    public const I_ALL = 31;

    public const E_NON_EMPTY_STRING_ALIAS = 1;
    public const M_NON_EMPTY_STRING_ALIAS = "Invalid alias: non-empty string required at arguments 1 and 2";
    public const E_SHARED_CANNOT_ALIAS = 2;
    public const M_SHARED_CANNOT_ALIAS = "Cannot alias class %s to %s because it is currently shared";
    public const E_SHARE_ARGUMENT = 3;
    public const M_SHARE_ARGUMENT = "%s::share() requires a string class name or object instance at Argument 1; %s specified";
    public const E_ALIASED_CANNOT_SHARE = 4;
    public const M_ALIASED_CANNOT_SHARE = "Cannot share class %s because it is currently aliased to %s";
    public const E_INVOKABLE = 5;
    public const M_INVOKABLE = "Invalid invokable: callable or provisional string required";
    public const E_NON_PUBLIC_CONSTRUCTOR = 6;
    public const M_NON_PUBLIC_CONSTRUCTOR = "Cannot instantiate protected/private constructor in class %s";
    public const E_NEEDS_DEFINITION = 7;
    public const M_NEEDS_DEFINITION = "Injection definition required for %s %s";
    public const E_MAKE_FAILURE = 8;
    public const M_MAKE_FAILURE = "Could not make %s: %s";
    public const E_UNDEFINED_PARAM = 9;
    public const M_UNDEFINED_PARAM = "No definition available to provision typeless parameter \$%s at position %d in %s()%s";
    public const E_DELEGATE_ARGUMENT = 10;
    public const M_DELEGATE_ARGUMENT = "%s::delegate expects a valid callable or executable class::method string at Argument 2%s";
    public const E_CYCLIC_DEPENDENCY = 11;
    public const M_CYCLIC_DEPENDENCY = "Detected a cyclic dependency while provisioning %s";
    public const E_MAKING_FAILED = 12;
    public const M_MAKING_FAILED = "Making %s did not result in an object, instead result is of type '%s'";

    private $reflector;
    private $classDefinitions = array();
    private $paramDefinitions = array();
    private $aliases = array();
    private $shares = array();
    private $prepares = array();
    private $delegates = array();
    private $inProgressMakes = array();

    public function __construct(?Reflector $reflector = null)
    {
        $this->reflector = $reflector ?: new CachingReflector();
    }

    public function __clone()
    {
        $this->inProgressMakes = array();
    }

    /**
     * Define instantiation directives for the specified class
     *
     * @param string $name The class (or alias) whose constructor arguments we wish to define
     * @param array $args An array mapping parameter names to values/instructions
     * @return self
     */
    public function define($name, array $args)
    {
        list(, $normalizedName) = $this->resolveAlias($name);
        $this->classDefinitions[$normalizedName] = $args;

        return $this;
    }

    /**
     * Assign a global default value for all parameters named $paramName
     *
     * Global parameter definitions are only used for parameters with no typehint, pre-defined or
     * call-time definition.
     *
     * @param string $paramName The parameter name for which this value applies
     * @param mixed $value The value to inject for this parameter name
     * @return self
     */
    public function defineParam($paramName, $value)
    {
        $this->paramDefinitions[$paramName] = $value;

        return $this;
    }

    /**
     * Define an alias for all occurrences of a given typehint
     *
     * Use this method to specify implementation classes for interface and abstract class typehints.
     *
     * @param string $original The typehint to replace
     * @param string $alias The implementation name
     * @throws ConfigException if any argument is empty or not a string
     * @return self
     */
    public function alias($original, $alias)
    {
        if (empty($original) || !is_string($original)) {
            throw new ConfigException(
                self::M_NON_EMPTY_STRING_ALIAS,
                self::E_NON_EMPTY_STRING_ALIAS
            );
        }
        if (empty($alias) || !is_string($alias)) {
            throw new ConfigException(
                self::M_NON_EMPTY_STRING_ALIAS,
                self::E_NON_EMPTY_STRING_ALIAS
            );
        }

        $originalNormalized = $this->normalizeName($original);

        if (isset($this->shares[$originalNormalized])) {
            throw new ConfigException(
                sprintf(
                    self::M_SHARED_CANNOT_ALIAS,
                    $this->normalizeName(get_class($this->shares[$originalNormalized])),
                    $alias
                ),
                self::E_SHARED_CANNOT_ALIAS
            );
        }

        if (array_key_exists($originalNormalized, $this->shares)) {
            $aliasNormalized = $this->normalizeName($alias);
            $this->shares[$aliasNormalized] = null;
            unset($this->shares[$originalNormalized]);
        }

        $this->aliases[$originalNormalized] = $alias;

        return $this;
    }

    private function normalizeName($className)
    {
        return ltrim(strtolower($className), '\\?');
    }

    /**
     * Share the specified class/instance across the Injector context
     *
     * @param mixed $nameOrInstance The class or object to share
     * @throws ConfigException if $nameOrInstance is not a string or an object
     * @return self
     */
    public function share($nameOrInstance)
    {
        if (is_string($nameOrInstance)) {
            $this->shareClass($nameOrInstance);
        } elseif (is_object($nameOrInstance)) {
            $this->shareInstance($nameOrInstance);
        } else {
            throw new ConfigException(
                sprintf(
                    self::M_SHARE_ARGUMENT,
                    __CLASS__,
                    gettype($nameOrInstance)
                ),
                self::E_SHARE_ARGUMENT
            );
        }

        return $this;
    }

    private function shareClass($nameOrInstance)
    {
        list(, $normalizedName) = $this->resolveAlias($nameOrInstance);
        $this->shares[$normalizedName] = isset($this->shares[$normalizedName])
            ? $this->shares[$normalizedName]
            : null;
    }

    private function resolveAlias($name)
    {
        $normalizedName = $this->normalizeName($name);
        if (isset($this->aliases[$normalizedName])) {
            $name = $this->aliases[$normalizedName];
            $normalizedName = $this->normalizeName($name);
        }

        return array($name, $normalizedName);
    }

    private function shareInstance($obj)
    {
        $normalizedName = $this->normalizeName(get_class($obj));
        if (isset($this->aliases[$normalizedName])) {
            // You cannot share an instance of a class name that is already aliased
            throw new ConfigException(
                sprintf(
                    self::M_ALIASED_CANNOT_SHARE,
                    $normalizedName,
                    $this->aliases[$normalizedName]
                ),
                self::E_ALIASED_CANNOT_SHARE
            );
        }
        $this->shares[$normalizedName] = $obj;
    }

    /**
     * Register a prepare callable to modify/prepare objects of type $name after instantiation
     *
     * Any callable or provisionable invokable may be specified. Preparers are passed two
     * arguments: the instantiated object to be mutated and the current Injector instance.
     *
     * @param string $name
     * @param mixed $callableOrMethodStr Any callable or provisionable invokable method
     * @throws InjectionException if $callableOrMethodStr is not a callable.
     *                            See https://github.com/rdlowrey/auryn#injecting-for-execution
     * @return self
     */
    public function prepare($name, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        list(, $normalizedName) = $this->resolveAlias($name);
        $this->prepares[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

    private function isExecutable($exe)
    {
        if (is_callable($exe)) {
            return true;
        }
        if (is_string($exe) && method_exists($exe, '__invoke')) {
            return true;
        }
        if (is_array($exe) && isset($exe[0], $exe[1]) && method_exists($exe[0], $exe[1])) {
            return true;
        }

        return false;
    }

    /**
     * Delegate the creation of $name instances to the specified callable
     *
     * @param string $name
     * @param mixed $callableOrMethodStr Any callable or provisionable invokable method
     * @throws ConfigException if $callableOrMethodStr is not a callable.
     * @return self
     */
    public function delegate($name, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            $errorDetail = '';
            if (is_string($callableOrMethodStr)) {
                $errorDetail = " but received '$callableOrMethodStr'";
            } elseif (is_array($callableOrMethodStr) &&
                count($callableOrMethodStr) === 2 &&
                array_key_exists(0, $callableOrMethodStr) &&
                array_key_exists(1, $callableOrMethodStr)
            ) {
                if (is_string($callableOrMethodStr[0]) && is_string($callableOrMethodStr[1])) {
                    $errorDetail = " but received ['".$callableOrMethodStr[0]."', '".$callableOrMethodStr[1]."']";
                }
            }
            throw new ConfigException(
                sprintf(self::M_DELEGATE_ARGUMENT, __CLASS__, $errorDetail),
                self::E_DELEGATE_ARGUMENT
            );
        }
        $normalizedName = $this->normalizeName($name);
        $this->delegates[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

    /**
     * Retrieve stored data for the specified definition type
     *
     * Exposes introspection of existing binds/delegates/shares/etc for decoration and composition.
     *
     * @param string $nameFilter An optional class name filter
     * @param int $typeFilter A bitmask of Injector::* type constant flags
     * @return array
     */
    public function inspect($nameFilter = null, $typeFilter = null)
    {
        $result = array();
        $name = $nameFilter ? $this->normalizeName($nameFilter) : null;

        if (empty($typeFilter)) {
            $typeFilter = self::I_ALL;
        }

        $types = array(
            self::I_BINDINGS => "classDefinitions",
            self::I_DELEGATES => "delegates",
            self::I_PREPARES => "prepares",
            self::I_ALIASES => "aliases",
            self::I_SHARES => "shares"
        );

        foreach ($types as $type => $source) {
            if ($typeFilter & $type) {
                $result[$type] = $this->filter($this->{$source}, $name);
            }
        }

        return $result;
    }

    private function filter($source, $name)
    {
        if (empty($name)) {
            return $source;
        } elseif (array_key_exists($name, $source)) {
            return array($name => $source[$name]);
        } else {
            return array();
        }
    }

    /**
     * Instantiate/provision a class instance
     *
     * @param string $name
     * @param array $args
     * @throws InjectionException if a cyclic gets detected when provisioning
     * @return mixed
     */
    public function make($name, array $args = array())
    {
        list($className, $normalizedClass) = $this->resolveAlias($name);

        if (isset($this->inProgressMakes[$normalizedClass])) {
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(
                    self::M_CYCLIC_DEPENDENCY,
                    $className
                ),
                self::E_CYCLIC_DEPENDENCY
            );
        }

        $this->inProgressMakes[$normalizedClass] = count($this->inProgressMakes);

        // isset() is used specifically here because classes may be marked as "shared" before an
        // instance is stored. In these cases the class is "shared," but it has a null value and
        // instantiation is needed.
        if (isset($this->shares[$normalizedClass])) {
            unset($this->inProgressMakes[$normalizedClass]);

            return $this->shares[$normalizedClass];
        }

        try {
            if (isset($this->delegates[$normalizedClass])) {
                $executable = $this->buildExecutable($this->delegates[$normalizedClass]);
                $reflectionFunction = $executable->getCallableReflection();
                $args = $this->provisionFuncArgs($reflectionFunction, $args, null, $className);
                $obj = call_user_func_array(array($executable, '__invoke'), $args);
            } else {
                $obj = $this->provisionInstance($className, $normalizedClass, $args);
            }

            $obj = $this->prepareInstance($obj, $normalizedClass);

            if (array_key_exists($normalizedClass, $this->shares)) {
                $this->shares[$normalizedClass] = $obj;
            }

            unset($this->inProgressMakes[$normalizedClass]);
        } catch (\Throwable $exception) {
            unset($this->inProgressMakes[$normalizedClass]);
            throw $exception;
        }

        return $obj;
    }

    private function provisionInstance($className, $normalizedClass, array $definition)
    {
        try {
            $ctor = $this->reflector->getCtor($className);

            if (!$ctor) {
                $obj = $this->instantiateWithoutCtorParams($className);
            } elseif (!$ctor->isPublic()) {
                throw new InjectionException(
                    $this->inProgressMakes,
                    sprintf(self::M_NON_PUBLIC_CONSTRUCTOR, $className),
                    self::E_NON_PUBLIC_CONSTRUCTOR
                );
            } elseif ($ctorParams = $this->reflector->getCtorParams($className)) {
                $reflClass = $this->reflector->getClass($className);
                $definition = isset($this->classDefinitions[$normalizedClass])
                    ? array_replace($this->classDefinitions[$normalizedClass], $definition)
                    : $definition;
                $args = $this->provisionFuncArgs($ctor, $definition, $ctorParams, $className);
                $obj = $reflClass->newInstanceArgs($args);
            } else {
                $obj = $this->instantiateWithoutCtorParams($className);
            }

            return $obj;
        } catch (\ReflectionException $e) {
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(self::M_MAKE_FAILURE, $className, $e->getMessage()),
                self::E_MAKE_FAILURE,
                $e
            );
        }
    }

    private function instantiateWithoutCtorParams($className)
    {
        $reflClass = $this->reflector->getClass($className);

        if (!$reflClass->isInstantiable()) {
            $type = $reflClass->isInterface() ? 'interface' : 'abstract class';
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(self::M_NEEDS_DEFINITION, $type, $className),
                self::E_NEEDS_DEFINITION
            );
        }

        return new $className();
    }

    private function provisionFuncArgs(\ReflectionFunctionAbstract $reflFunc, array $definition, ?array $reflParams = null, $className = null)
    {
        $args = array();

        // @TODO store this in ReflectionStorage
        if (!isset($reflParams)) {
            $reflParams = $reflFunc->getParameters();
        }

        foreach ($reflParams as $i => $reflParam) {
            $name = $reflParam->name;

            if (isset($definition[$i]) || array_key_exists($i, $definition)) {
                // indexed arguments take precedence over named parameters
                $arg = $definition[$i];
            } elseif (isset($definition[$name]) || array_key_exists($name, $definition)) {
                // interpret the param as a class name to be instantiated
                $arg = $this->make($definition[$name]);
            } elseif (($prefix = self::A_RAW . $name) && (isset($definition[$prefix]) || array_key_exists($prefix, $definition))) {
                // interpret the param as a raw value to be injected
                $arg = $definition[$prefix];
            } elseif (($prefix = self::A_DELEGATE . $name) && isset($definition[$prefix])) {
                // interpret the param as an invokable delegate
                $arg = $this->buildArgFromDelegate($name, $definition[$prefix]);
            } elseif (($prefix = self::A_DEFINE . $name) && isset($definition[$prefix])) {
                // interpret the param as a class definition
                $arg = $this->buildArgFromParamDefineArr($definition[$prefix]);
            } elseif (!$arg = $this->buildArgFromTypeHint($reflFunc, $reflParam)) {
                $arg = $this->buildArgFromReflParam($reflParam, $className);

                if ($arg === null && PHP_VERSION_ID >= 50600 && $reflParam->isVariadic()) {
                    // buildArgFromReflParam might return null in case the parameter is optional
                    // in case of variadics, the parameter is optional, but null might not be allowed
                    continue;
                }
            }

            $args[] = $arg;
        }

        return $args;
    }

    private function buildArgFromParamDefineArr($definition)
    {
        if (!is_array($definition)) {
            throw new InjectionException(
                $this->inProgressMakes
                // @TODO Add message
            );
        }

        if (!isset($definition[0], $definition[1])) {
            throw new InjectionException(
                $this->inProgressMakes
                // @TODO Add message
            );
        }

        list($class, $definition) = $definition;

        return $this->make($class, $definition);
    }

    private function buildArgFromDelegate($paramName, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        $executable = $this->buildExecutable($callableOrMethodStr);

        return $executable($paramName, $this);
    }

    private function buildArgFromTypeHint(\ReflectionFunctionAbstract $reflFunc, \ReflectionParameter $reflParam)
    {
        $typeHint = $this->reflector->getParamTypeHint($reflFunc, $reflParam);

        if (!$typeHint) {
            $obj = null;
        } elseif ($reflParam->isDefaultValueAvailable()) {
            $normalizedName = $this->normalizeName($typeHint);
            // Injector has been told explicitly how to make this type
            if (isset($this->aliases[$normalizedName]) ||
                isset($this->delegates[$normalizedName]) ||
                isset($this->shares[$normalizedName])) {
                $obj = $this->make($typeHint);
            } else {
                $obj = $reflParam->getDefaultValue();
            }
        } else {
            $obj = $this->make($typeHint);
        }

        return $obj;
    }

    private function buildArgFromReflParam(\ReflectionParameter $reflParam, $className = null)
    {
        if (array_key_exists($reflParam->name, $this->paramDefinitions)) {
            $arg = $this->paramDefinitions[$reflParam->name];
        } elseif ($reflParam->isDefaultValueAvailable()) {
            $arg = $reflParam->getDefaultValue();
        } elseif ($reflParam->isOptional()) {
            // This branch is required to work around PHP bugs where a parameter is optional
            // but has no default value available through reflection. Specifically, PDO exhibits
            // this behavior.
            $arg = null;
        } else {
            $reflFunc = $reflParam->getDeclaringFunction();
            $classDeclare = ($reflFunc instanceof \ReflectionMethod)
                ? " declared in " . $reflFunc->getDeclaringClass()->name . "::"
                : "";
            $classWord = ($reflFunc instanceof \ReflectionMethod)
                ? $className . '::'
                : '';
            $funcWord = $classWord . $reflFunc->name;

            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(
                    self::M_UNDEFINED_PARAM,
                    $reflParam->name,
                    $reflParam->getPosition(),
                    $funcWord,
                    $classDeclare
                ),
                self::E_UNDEFINED_PARAM
            );
        }

        return $arg;
    }

    private function prepareInstance($obj, $normalizedClass)
    {
        if (isset($this->prepares[$normalizedClass])) {
            $prepare = $this->prepares[$normalizedClass];
            $executable = $this->buildExecutable($prepare);
            $result = $executable($obj, $this);
            if ($result instanceof $normalizedClass) {
                $obj = $result;
            }
        }

        if ($obj !== null) {
            $interfaces = @class_implements($obj);
        } else {
            $interfaces = false;
        }

        if ($interfaces === false) {
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(
                    self::M_MAKING_FAILED,
                    $normalizedClass,
                    gettype($obj)
                ),
                self::E_MAKING_FAILED
            );
        }

        if (empty($interfaces)) {
            return $obj;
        }

        $interfaces = array_flip(array_map(array($this, 'normalizeName'), $interfaces));
        $prepares = array_intersect_key($this->prepares, $interfaces);
        foreach ($prepares as $interfaceName => $prepare) {
            $executable = $this->buildExecutable($prepare);
            $result = $executable($obj, $this);
            if ($result instanceof $normalizedClass) {
                $obj = $result;
            }
        }

        return $obj;
    }

    /**
     * Invoke the specified callable or class::method string, provisioning dependencies along the way
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     * @param array $args Optional array specifying params with which to invoke the provisioned callable
     * @throws \Auryn\InjectionException
     * @return mixed Returns the invocation result returned from calling the generated executable
     */
    public function execute($callableOrMethodStr, array $args = array())
    {
        list($reflFunc, $invocationObj) = $this->buildExecutableStruct($callableOrMethodStr);
        $executable = new Executable($reflFunc, $invocationObj);
        $args = $this->provisionFuncArgs($reflFunc, $args, null, $invocationObj === null ? null : get_class($invocationObj));

        return call_user_func_array(array($executable, '__invoke'), $args);
    }

    /**
     * Provision an Executable instance from any valid callable or class::method string
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     * @return \Auryn\Executable
     */
    public function buildExecutable($callableOrMethodStr)
    {
        try {
            list($reflFunc, $invocationObj) = $this->buildExecutableStruct($callableOrMethodStr);
        } catch (\ReflectionException $e) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr,
                $e
            );
        }

        return new Executable($reflFunc, $invocationObj);
    }

    private function buildExecutableStruct($callableOrMethodStr)
    {
        if (is_string($callableOrMethodStr)) {
            $executableStruct = $this->buildExecutableStructFromString($callableOrMethodStr);
        } elseif ($callableOrMethodStr instanceof \Closure) {
            $callableRefl = new \ReflectionFunction($callableOrMethodStr);
            $executableStruct = array($callableRefl, null);
        } elseif (is_object($callableOrMethodStr) && is_callable($callableOrMethodStr)) {
            $invocationObj = $callableOrMethodStr;
            $callableRefl = $this->reflector->getMethod($invocationObj, '__invoke');
            $executableStruct = array($callableRefl, $invocationObj);
        } elseif (is_array($callableOrMethodStr)
            && isset($callableOrMethodStr[0], $callableOrMethodStr[1])
            && count($callableOrMethodStr) === 2
        ) {
            $executableStruct = $this->buildExecutableStructFromArray($callableOrMethodStr);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        return $executableStruct;
    }

    private function buildExecutableStructFromString($stringExecutable)
    {
        if (function_exists($stringExecutable)) {
            $callableRefl = $this->reflector->getFunction($stringExecutable);
            $executableStruct = array($callableRefl, null);
        } elseif (method_exists($stringExecutable, '__invoke')) {
            $invocationObj = $this->make($stringExecutable);
            $callableRefl = $this->reflector->getMethod($invocationObj, '__invoke');
            $executableStruct = array($callableRefl, $invocationObj);
        } elseif (strpos($stringExecutable, '::') !== false) {
            list($class, $method) = explode('::', $stringExecutable, 2);
            $executableStruct = $this->buildStringClassMethodCallable($class, $method);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $stringExecutable
            );
        }

        return $executableStruct;
    }

    private function buildStringClassMethodCallable($class, $method)
    {
        $relativeStaticMethodStartPos = strpos($method, 'parent::');

        if ($relativeStaticMethodStartPos === 0) {
            $childReflection = $this->reflector->getClass($class);
            $class = $childReflection->getParentClass()->name;
            $method = substr($method, $relativeStaticMethodStartPos + 8);
        }

        list($className, $normalizedClass) = $this->resolveAlias($class);
        $reflectionMethod = $this->reflector->getMethod($className, $method);

        if ($reflectionMethod->isStatic()) {
            return array($reflectionMethod, null);
        }

        $instance = $this->make($className);
        // If the class was delegated, the instance may not be of the type
        // $class but some other type. We need to get the reflection on the
        // actual class to be able to call the method correctly.
        $reflectionMethod = $this->reflector->getMethod($instance, $method);

        return array($reflectionMethod, $instance);
    }

    private function buildExecutableStructFromArray($arrayExecutable)
    {
        list($classOrObj, $method) = $arrayExecutable;

        if (is_object($classOrObj) && method_exists($classOrObj, $method)) {
            $callableRefl = $this->reflector->getMethod($classOrObj, $method);
            $executableStruct = array($callableRefl, $classOrObj);
        } elseif (is_string($classOrObj)) {
            $executableStruct = $this->buildStringClassMethodCallable($classOrObj, $method);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $arrayExecutable
            );
        }

        return $executableStruct;
    }
}
