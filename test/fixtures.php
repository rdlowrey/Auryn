<?php

namespace Auryn;

class InaccessibleExecutableClassMethod
{
    private function doSomethingPrivate()
    {
        return 42;
    }
    protected function doSomethingProtected()
    {
        return 42;
    }
}

class InaccessibleStaticExecutableClassMethod
{
    private static function doSomethingPrivate()
    {
        return 42;
    }
    protected static function doSomethingProtected()
    {
        return 42;
    }
}

class ClassWithStaticMethodThatTakesArg
{
    public static function doSomething($arg)
    {
        return 1 + $arg;
    }
}

class RecursiveClass1
{
    public function __construct(RecursiveClass2 $dep)
    {
    }
}

class RecursiveClass2
{
    public function __construct(RecursiveClass1 $dep)
    {
    }
}

class RecursiveClassA
{
    public function __construct(RecursiveClassB $b)
    {
    }
}

class RecursiveClassB
{
    public function __construct(RecursiveClassC $c)
    {
    }
}

class RecursiveClassC
{
    public function __construct(RecursiveClassA $a)
    {
    }
}

class DependsOnCyclic
{
    public function __construct(RecursiveClassA $a)
    {
    }
}

interface SharedAliasedInterface
{
    public function foo();
}

class SharedClass implements SharedAliasedInterface
{
    public function foo()
    {
    }
}

class NotSharedClass implements SharedAliasedInterface
{
    public function foo()
    {
    }
}


class DependencyWithDefinedParam
{
    public $foo;
    public function __construct($foo)
    {
        $this->foo = $foo;
    }
}

class RequiresDependencyWithDefinedParam
{
    public $obj;
    public function __construct(DependencyWithDefinedParam $obj)
    {
        $this->obj = $obj;
    }
}


class ClassWithAliasAsParameter
{
    public $sharedClass;

    public function __construct(SharedClass $sharedClass)
    {
        $this->sharedClass = $sharedClass;
    }
}

class ConcreteClass1
{
}

class ConcreteClass2
{
}

class ClassWithoutMagicInvoke
{
}

class TestNoConstructor
{
}

class TestDependency
{
    public $testProp = 'testVal';
}

class TestDependency2 extends TestDependency
{
    public $testProp = 'testVal2';
}

class SpecdTestDependency extends TestDependency
{
    public $testProp = 'testVal';
}

class TestNeedsDep
{
    public $testDep;
    public function __construct(TestDependency $testDep)
    {
        $this->testDep = $testDep;
    }
}

class TestClassWithNoCtorTypehints
{
    public function __construct($val = 42)
    {
        $this->test = $val;
    }
}

class TestMultiDepsNeeded
{
    public $testDep;

    public function __construct(TestDependency $val1, TestDependency2 $val2)
    {
        $this->testDep = $val1;
        $this->testDep = $val2;
    }
}


class TestMultiDepsWithCtor
{
    public $testDep;

    public function __construct(TestDependency $val1, TestNeedsDep $val2)
    {
        $this->testDep = $val1;
        $this->testDep = $val2;
    }
}

class NoTypehintNullDefaultConstructorClass
{
    public $testParam = 1;
    public function __construct(TestDependency $val1, $arg=42)
    {
        $this->testParam = $arg;
    }
}

class NoTypehintNoDefaultConstructorClass
{
    public $testParam = 1;
    public function __construct(TestDependency $val1, $arg = null)
    {
        $this->testParam = $arg;
    }
}

interface DepInterface
{
}
interface SomeInterface
{
}
class SomeImplementation implements SomeInterface
{
}
class PreparesImplementationTest implements SomeInterface
{
    public $testProp = 0;
}

class DepImplementation implements DepInterface
{
    public $testProp = 'something';
}

class RequiresInterface
{
    public $dep;
    public $testDep;
    public function __construct(DepInterface $dep)
    {
        $this->testDep = $dep;
    }
}

class ClassInnerA
{
    public $dep;
    public function __construct(ClassInnerB $dep)
    {
        $this->dep = $dep;
    }
}
class ClassInnerB
{
    public function __construct()
    {
    }
}
class ClassOuter
{
    public $dep;
    public function __construct(ClassInnerA $dep)
    {
        $this->dep = $dep;
    }
}

class ProvTestNoDefinitionNullDefaultClass
{
    public function __construct($arg = null)
    {
        $this->arg = $arg;
    }
}

interface TestNoExplicitDefine
{
}

class InjectorTestCtorParamWithNoTypehintOrDefault implements TestNoExplicitDefine
{
    public $val = 42;
    public function __construct($val)
    {
        $this->val = $val;
    }
}

class InjectorTestCtorParamWithNoTypehintOrDefaultDependent
{
    private $param;
    public function __construct(TestNoExplicitDefine $param)
    {
        $this->param = $param;
    }
}

class InjectorTestRawCtorParams
{
    public $string;
    public $obj;
    public $int;
    public $array;
    public $float;
    public $bool;
    public $null;

    public function __construct($string, $obj, $int, $array, $float, $bool, $null)
    {
        $this->string = $string;
        $this->obj = $obj;
        $this->int = $int;
        $this->array = $array;
        $this->float = $float;
        $this->bool = $bool;
        $this->null = $null;
    }
}

class InjectorTestParentClass
{
    public function __construct($arg1)
    {
        $this->arg1 = $arg1;
    }
}

class InjectorTestChildClass extends InjectorTestParentClass
{
    public function __construct($arg1, $arg2)
    {
        parent::__construct($arg1);
        $this->arg2 = $arg2;
    }
}

class CallableMock
{
    public function __invoke()
    {
    }
}

class ProviderTestCtorParamWithNoTypehintOrDefault implements TestNoExplicitDefine
{
    public $val = 42;
    public function __construct($val)
    {
        $this->val = $val;
    }
}

class ProviderTestCtorParamWithNoTypehintOrDefaultDependent
{
    private $param;
    public function __construct(TestNoExplicitDefine $param)
    {
        $this->param = $param;
    }
}

class StringStdClassDelegateMock
{
    public function __invoke()
    {
        return $this->make();
    }
    private function make()
    {
        $obj = new \stdClass();
        $obj->test = 42;
        return $obj;
    }
}

class StringDelegateWithNoInvokeMethod
{
}

class ExecuteClassNoDeps
{
    public function execute()
    {
        return 42;
    }
}

class ExecuteClassDeps
{
    public function __construct(TestDependency $testDep)
    {
    }
    public function execute()
    {
        return 42;
    }
}

class ExecuteClassDepsWithMethodDeps
{
    public function __construct(TestDependency $testDep)
    {
    }
    public function execute(TestDependency $dep, $arg = null)
    {
        return isset($arg) ? $arg : 42;
    }
}

class ExecuteClassStaticMethod
{
    public static function execute()
    {
        return 42;
    }
}

class ExecuteClassRelativeStaticMethod extends ExecuteClassStaticMethod
{
    public static function execute()
    {
        return 'this should NEVER be seen since we are testing against parent::execute()';
    }
}

class ExecuteClassInvokable
{
    public function __invoke()
    {
        return 42;
    }
}

function hasArrayDependency(array $parameter)
{
    return 42;
}

function testExecuteFunction()
{
    return 42;
}

function testExecuteFunctionWithArg(ConcreteClass1 $foo)
{
    return 42;
}

class MadeByDelegate
{
}

class CallableDelegateClassTest
{
    public function __invoke()
    {
        return new MadeByDelegate();
    }
}

interface DelegatableInterface
{
    public function foo();
}

class ImplementsInterface implements DelegatableInterface
{
    public function foo()
    {
    }
}

class ImplementsInterfaceFactory
{
    public function __invoke()
    {
        return new ImplementsInterface();
    }
}

class RequiresDelegatedInterface
{
    private $interface;

    public function __construct(DelegatableInterface $interface)
    {
        $this->interface = $interface;
    }
    public function foo()
    {
        $this->interface->foo();
    }
}

class TestMissingDependency
{
    public function __construct(TypoInTypehint $class)
    {
    }
}

class NonConcreteDependencyWithDefaultValue
{
    public $interface;
    public function __construct(?DelegatableInterface $i = null)
    {
        $this->interface = $i;
    }
}


class ConcreteDependencyWithDefaultValue
{
    public $dependency;
    public function __construct(?\StdClass $instance = null)
    {
        $this->dependency = $instance;
    }
}

class TypelessParameterDependency
{
    public $thumbnailSize;

    public function __construct($thumbnailSize)
    {
        $this->thumbnailSize = $thumbnailSize;
    }
}

class RequiresDependencyWithTypelessParameters
{
    public $dependency;

    public function __construct(TypelessParameterDependency $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getThumbnailSize()
    {
        return $this->dependency->thumbnailSize;
    }
}

class HasNonPublicConstructor
{
    protected function __construct()
    {
    }
}

class HasNonPublicConstructorWithArgs
{
    protected function __construct($arg1, $arg2, $arg3)
    {
    }
}

class ClassWithCtor
{
    public function __construct()
    {
    }
}

class TestDependencyWithProtectedConstructor
{
    protected function __construct()
    {
    }

    public static function create()
    {
        return new self();
    }
}

class TestNeedsDepWithProtCons
{
    public function __construct(TestDependencyWithProtectedConstructor $dep)
    {
        $this->dep = $dep;
    }
}

class SimpleNoTypehintClass
{
    public $testParam = 1;

    public function __construct($arg)
    {
        $this->testParam = $arg;
    }
}

class SomeClassName
{
}

class TestDelegationSimple
{
    public $delegateCalled = false;
}

class TestDelegationDependency
{
    public $delegateCalled = false;
    public function __construct(TestDelegationSimple $testDelegationSimple)
    {
    }
}

function createTestDelegationSimple()
{
    $instance = new TestDelegationSimple();
    $instance->delegateCalled = true;

    return $instance;
}

function createTestDelegationDependency(TestDelegationSimple $testDelegationSimple)
{
    $instance = new TestDelegationDependency($testDelegationSimple);
    $instance->delegateCalled = true;

    return $instance;
}


class BaseExecutableClass
{
    public function foo()
    {
        return 'This is the BaseExecutableClass';
    }
    public static function bar()
    {
        return 'This is the BaseExecutableClass';
    }
}

class ExtendsExecutableClass extends BaseExecutableClass
{
    public function foo()
    {
        return 'This is the ExtendsExecutableClass';
    }
    public static function bar()
    {
        return 'This is the ExtendsExecutableClass';
    }
}

class ReturnsCallable
{
    private $value = 'original';

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getCallable()
    {
        $callable = function () {
            return $this->value;
        };

        return $callable;
    }
}

class DelegateClosureInGlobalScope
{
}

function getDelegateClosureInGlobalScope()
{
    return function () {
        return new DelegateClosureInGlobalScope();
    };
}

class CloneTest
{
    public $injector;
    public function __construct(\Auryn\Injector $injector)
    {
        $this->injector = clone $injector;
    }
}

abstract class AbstractExecuteTest
{
    public function process()
    {
        return "Abstract";
    }
}

class ConcreteExexcuteTest extends AbstractExecuteTest
{
    public function process()
    {
        return "Concrete";
    }
}

class DependencyChainTest
{
    public function __construct(DepInterface $dep)
    {
    }
}

class ParentWithConstructor
{
    public $foo;
    public function __construct($foo)
    {
        $this->foo = $foo;
    }
}

class ChildWithoutConstructor extends ParentWithConstructor
{
}

class DelegateA
{
}
class DelegatingInstanceA
{
    public function __construct(DelegateA $a)
    {
        $this->a = $a;
    }
}

class DelegateB
{
}
class DelegatingInstanceB
{
    public function __construct(DelegateB $b)
    {
        $this->b = $b;
    }
}

class ThrowsExceptionInConstructor
{
    public function __construct()
    {
        throw new \Exception('Exception in constructor');
    }
}
