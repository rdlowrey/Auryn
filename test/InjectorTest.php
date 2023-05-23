<?php

namespace Auryn\Test;

use Auryn\Injector;

class InjectorTest extends BaseTest
{
    public function testArrayTypeDoesNotEvaluatesAsClass()
    {
        $injector = new Injector;
        $injector->defineParam('parameter', []);
        $injector->execute('Auryn\Test\hasArrayDependency');
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $injector->make('Auryn\Test\TestNeedsDep')
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNoConstructor, $injector->make('Auryn\Test\TestNoConstructor'));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteType()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $this->assertEquals(new DepImplementation, $injector->make('Auryn\Test\DepInterface'));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");
        $this->expectExceptionCode(\Auryn\Injector::E_NEEDS_DEFINITION);
        $injector = new Injector;
        $injector->make('Auryn\Test\DepInterface');
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");
        $this->expectExceptionCode(\Auryn\Injector::E_NEEDS_DEFINITION);

        $injector = new Injector;
        $injector->make('Auryn\Test\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $obj = $injector->make('Auryn\Test\RequiresInterface');
        $this->assertInstanceOf('Auryn\Test\RequiresInterface', $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypeOrDefaultCanBeDetermined()
    {
        $injector = new Injector;
        $nullCtorParamObj = $injector->make('Auryn\Test\ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertNull($nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\RequiresInterface', array('dep' => 'Auryn\Test\DepImplementation'));
        $injector->share('Auryn\Test\RequiresInterface');
        $injected = $injector->make('Auryn\Test\RequiresInterface');

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make('Auryn\Test\RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $classname = 'ClassThatDoesntExist';
        if (PHP_VERSION_ID >= 80000) {
            $classname = "\"" . $classname . "\"";
        }

        $this->expectException(\Auryn\InjectorException::class);
        $this->expectExceptionMessage("Could not make ClassThatDoesntExist: Class $classname does not exist");

        $injector = new Injector;
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\TestNeedsDep', array('testDep'=>'Auryn\Test\TestDependency'));
        $injected = $injector->make('Auryn\Test\TestNeedsDep', array('testDep'=>'Auryn\Test\TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\InjectorTestChildClass', array(':arg1'=>'First argument', ':arg2'=>'Second argument'));
        $injected = $injector->make('Auryn\Test\InjectorTestChildClass', array(':arg1'=>'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Injector;
        $injector->share('Auryn\Test\TestDependency');
        $injector->make('Auryn\Test\TestDependency');
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\TestMultiDepsWithCtor', array('val1'=>'Auryn\Test\TestDependency'));
        $this->assertInstanceOf('Auryn\Test\TestMultiDepsWithCtor', $obj);

        $obj = $injector->make('Auryn\Test\NoTypeNoDefaultConstructorClass',
            array('val1'=>'Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf('Auryn\Test\NoTypeNoDefaultConstructorClass', $obj);
        $this->assertNull($obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\NoTypeNoDefaultConstructorVariadicClass',
            array('val1'=>'Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf('Auryn\Test\NoTypeNoDefaultConstructorVariadicClass', $obj);
        $this->assertEquals(array(), $obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithType()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\TypeNoDefaultConstructorVariadicClass',
            array('arg'=>'Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf('Auryn\Test\TypeNoDefaultConstructorVariadicClass', $obj);
        $this->assertIsArray($obj->testParam);
        $this->assertInstanceOf('Auryn\Test\TestDependency', $obj->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypedParameterWithoutDefinitionOrDefault()
    {
        $this->expectException(\Auryn\InjectionException::class);
        // TODO - why does this message end with double-colon?
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefault::__construct() declared in Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefault::');
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);

        $injector = new Injector;
        $injector->make('Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefault');
    }

    public function testbuildArgFromReflParamCoverageNonClassCase()
    {
        $injector= new Injector;

        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);
        $injector->execute('Auryn\Test\aFunctionWithAParam');
    }

    public function testMakeInstanceThrowsExceptionOnUntypedParameterWithoutDefinitionOrDefaultThroughAliasedType()
    {
        $this->expectException(\Auryn\InjectionException::class);
        // TODO - why does this message end with double-colon?
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefault::__construct() declared in Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefault::');
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);

        $injector = new Injector;
        $injector->alias('Auryn\Test\TestNoExplicitDefine', 'Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefault');
        $injector->make('Auryn\Test\InjectorTestCtorParamWithNoTypeOrDefaultDependent');
    }

    public function testMakeInstanceThrowsExceptionOnUninstantiableTypeWithoutDefinition()
    {
        $this->expectException(\Auryn\InjectorException::class);
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");

        $injector = new Injector;
        $injector->make('Auryn\Test\RequiresInterface');
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector = new Injector;
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make('Auryn\Test\RequiresDependencyWithTypelessParameters');
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(), 'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Injector;
        $injector->defineParam('val', 42);

        $injector->alias('Auryn\Test\TestNoExplicitDefine', 'Auryn\Test\ProviderTestCtorParamWithNoTypeOrDefault');
        $obj = $injector->make('Auryn\Test\ProviderTestCtorParamWithNoTypeOrDefaultDependent');
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\InjectorTestRawCtorParams', array(
            ':string' => 'string',
            ':obj' => new \StdClass,
            ':int' => 42,
            ':array' => array(),
            ':float' => 9.3,
            ':bool' => true,
            ':null' => null,
        ));

        $obj = $injector->make('Auryn\Test\InjectorTestRawCtorParams');
        $this->assertIsString($obj->string);
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertIsInt($obj->int);
        $this->assertIsArray($obj->array);
        $this->assertIsFloat($obj->float);
        $this->assertIsBool($obj->bool);
        $this->assertNull($obj->null);
    }

    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $injector= new Injector;

        $callable = $this->createPartialMock(
            'Auryn\test\CallableMock',
            array('__invoke')
        );

        $injector->delegate('TestDependency', $callable);

        $message = "This is the expected exception.";
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception($message)));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Injector;
        $injector->make('Auryn\Test\SomeClassName');
    }

    public function testMakeInstanceDelegate()
    {
        $injector= new Injector;

        $callable = $this->createPartialMock(
            'Auryn\test\CallableMock',
            array('__invoke')
        );

        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new TestDependency()));

        $injector->delegate('Auryn\Test\TestDependency', $callable);

        $obj = $injector->make('Auryn\Test\TestDependency');

        $this->assertInstanceOf('Auryn\Test\TestDependency', $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector= new Injector;
        $injector->delegate('StdClass', 'Auryn\Test\StringStdClassDelegateMock');
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod()
    {
        $injector= new Injector;

        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage("Auryn\\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'StringDelegateWithNoInvokeMethod'");

        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage("Auryn\\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'SomeClassThatDefinitelyDoesNotExistForReal'");

        $injector= new Injector;
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypedParameterWithNoDefinition()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Auryn\Test\DepInterface');

        $injector = new Injector;
        $injector->make('Auryn\Test\RequiresInterface');
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector = new Injector;
        $definition = array('dep' => 'Auryn\Test\DepImplementation');
        $injector->define('Auryn\Test\RequiresInterface', $definition);
        $this->assertInstanceOf('Auryn\Test\RequiresInterface', $injector->make('Auryn\Test\RequiresInterface'));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $testShare = new \StdClass;
        $testShare->test = 42;

        $this->assertInstanceOf('Auryn\Injector', $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter()
    {
        $injector = new Injector;
        $this->assertInstanceOf('Auryn\Injector', $injector->share('SomeClass'));
    }

    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage('Auryn\Injector::share() requires a string class name or object instance at Argument 1; integer specified');

        $injector = new Injector;
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $this->assertInstanceOf('Auryn\Injector', $injector->alias('DepInterface', 'Auryn\Test\DepImplementation'));
    }

    public function provideInvalidDelegates()
    {
        return array(
            array(new \StdClass),
            array(42),
            array(true)
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate)
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage('Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2');

        $injector = new Injector;
        $injector->delegate('Auryn\Test\TestDependency', $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\MadeByDelegate', 'Auryn\Test\CallableDelegateClassTest');
        $this->assertInstanceof('Auryn\Test\MadeByDelegate', $injector->make('Auryn\Test\MadeByDelegate'));
    }

    public function testDelegateInstantiatesCallableClassArray()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\MadeByDelegate', array('Auryn\Test\CallableDelegateClassTest', '__invoke'));
        $this->assertInstanceof('Auryn\Test\MadeByDelegate', $injector->make('Auryn\Test\MadeByDelegate'));
    }

    public function testUnknownDelegationFunction()
    {
        $injector = new Injector;
        try {
            $injector->delegate('Auryn\Test\DelegatableInterface', 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (\Auryn\InjectorException $ie) {
            $this->assertStringContainsString(
                'FunctionWhichDoesNotExist',
                $ie->getMessage()
            );
            $this->assertEquals(\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod()
    {
        $injector = new Injector;
        try {
            $injector->delegate('Auryn\Test\DelegatableInterface', array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (\Auryn\InjectorException $ie) {
            $this->assertStringContainsString(
                'stdClass',
                $ie->getMessage()
            );
            $this->assertStringContainsString(
                'methodWhichDoesNotExist',
                $ie->getMessage()
            );
            $this->assertEquals(\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $injector = new Injector;
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
    }

    public function provideExecutionExpectations()
    {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassNoDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassNoDeps, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassDeps(new TestDependency), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassDepsWithMethodDeps', 'execute');
        $args = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassStaticMethod', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassStaticMethod, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassRelativeStaticMethod', 'parent::execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\testExecuteFunction';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke = function () { return 42; };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke = new ExecuteClassInvokable;
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassInvokable';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassNoDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\testExecuteFunctionWithArg';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 18 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);


        if (PHP_VERSION_ID > 50400) {
            // 19 ------------------------------------------------------------------------------------->

            $object = new \Auryn\Test\ReturnsCallable('new value');
            $args = array();
            $toInvoke = $object->getCallable();
            $expectedResult = 'new value';
            $return[] = array($toInvoke, $args, $expectedResult);
        }
        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function testStaticStringInvokableWithArgument()
    {
        $injector = new \Auryn\Injector;
        $invokable = $injector->buildExecutable('Auryn\Test\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\DelegatableInterface', 'Auryn\Test\ImplementsInterfaceFactory');
        $requiresDelegatedInterface = $injector->make('Auryn\Test\RequiresDelegatedInterface');
        $requiresDelegatedInterface->foo();
    }

    public function testMissingAlias()
    {
        $reportedClassname = 'TestMissingDependency';
        $classname = 'Auryn\Test\TypoInType';
        if (PHP_VERSION_ID >= 80000) {
            $classname = "\"" . $classname . "\"";
            $reportedClassname = 'TypoInType';
        }

        $this->expectException(\Auryn\InjectorException::class);
        $this->expectExceptionMessage(
            "Could not make Auryn\\Test\\$reportedClassname: Class $classname does not exist"
        );

        $injector = new Injector;
        $testClass = $injector->make('Auryn\Test\TestMissingDependency');
    }

    public function testAliasingConcreteClasses()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\ConcreteClass1', 'Auryn\Test\ConcreteClass2');
        $obj = $injector->make('Auryn\Test\ConcreteClass1');
        $this->assertInstanceOf('Auryn\Test\ConcreteClass2', $obj);
    }

    public function testSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $injector->share('Auryn\Test\SharedAliasedInterface');
        $class = $injector->make('Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make('Auryn\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\NotSharedClass');
        $injector->share('Auryn\Test\SharedClass');
        $class = $injector->make('Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make('Auryn\Test\SharedAliasedInterface');

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder()
    {
        $injector = new Injector;
        $injector->share('Auryn\Test\SharedAliasedInterface');
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $class = $injector->make('Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make('Auryn\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $injector->share('Auryn\Test\SharedAliasedInterface');
        $sharedClass = $injector->make('Auryn\Test\SharedAliasedInterface');
        $childClass = $injector->make('Auryn\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $sharedClass = $injector->make('Auryn\Test\SharedAliasedInterface');
        $injector->share($sharedClass);
        $childClass = $injector->make('Auryn\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance()
    {
        $injector = new Injector;
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor()
    {
        $injector = new Injector;

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make('Auryn\Test\TestNeedsDepWithProtCons');

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared()
    {
        $injector = new Injector;
        $injector->share('Auryn\Test\ClassInnerB');
        $innerDep = $injector->make('Auryn\Test\ClassInnerB');
        $inner = $injector->make('Auryn\Test\ClassInnerA');
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make('Auryn\Test\ClassOuter');
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo()
    {
        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\ClassOuter');
        $this->assertInstanceOf('Auryn\Test\ClassOuter', $obj);
        $this->assertInstanceOf('Auryn\Test\ClassInnerA', $obj->dep);
        $this->assertInstanceOf('Auryn\Test\ClassInnerB', $obj->dep->dep);
    }

    public function provideCyclicDependencies()
    {
        return array(
            'Auryn\Test\RecursiveClassA' => array('Auryn\Test\RecursiveClassA'),
            'Auryn\Test\RecursiveClassB' => array('Auryn\Test\RecursiveClassB'),
            'Auryn\Test\RecursiveClassC' => array('Auryn\Test\RecursiveClassC'),
            'Auryn\Test\RecursiveClass1' => array('Auryn\Test\RecursiveClass1'),
            'Auryn\Test\RecursiveClass2' => array('Auryn\Test\RecursiveClass2'),
            'Auryn\Test\DependsOnCyclic' => array('Auryn\Test\DependsOnCyclic'),
        );
    }

     /**
     * @dataProvider provideCyclicDependencies
     */
    public function testCyclicDependencies($class)
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_CYCLIC_DEPENDENCY);

        $injector = new Injector;
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault()
    {
        $injector = new Injector;
        $class = $injector->make('Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias()
    {
        $injector = new Injector;
        $injector->alias(
            'Auryn\Test\DelegatableInterface',
            'Auryn\Test\ImplementsInterface'
        );
        $class = $injector->make('Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('Auryn\Test\ImplementsInterface', $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\DelegatableInterface', 'Auryn\Test\ImplementsInterfaceFactory');
        $class = $injector->make('Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('Auryn\Test\ImplementsInterface', $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare()
    {
        $injector = new Injector;
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make('Auryn\Test\ConcreteDependencyWithDefaultValue');
        $this->assertNull($instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $instance = new \StdClass();
        $injector->share($instance);
        $instance = $injector->make('Auryn\Test\ConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('StdClass', $instance->dependency);
    }

    public function testShareAfterAliasException()
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->alias('StdClass', 'Auryn\Test\SomeOtherClass');

        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage('Cannot share class stdclass because it is currently aliased to Auryn\Test\SomeOtherClass');
        $this->expectExceptionCode(\Auryn\Injector::E_ALIASED_CANNOT_SHARE);

        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed()
    {
        $injector = new Injector();
        $testClass = new DepImplementation();
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $injector->share($testClass);
        $obj = $injector->make('Auryn\Test\DepInterface');
        $this->assertInstanceOf('Auryn\Test\DepImplementation', $obj);
    }

    public function testAliasAfterShareByStringAllowed()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepInterface');
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $obj = $injector->make('Auryn\Test\DepInterface');
        $obj2 = $injector->make('Auryn\Test\DepInterface');
        $this->assertInstanceOf('Auryn\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepImplementation');
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $obj = $injector->make('Auryn\Test\DepInterface');
        $obj2 = $injector->make('Auryn\Test\DepInterface');
        $this->assertInstanceOf('Auryn\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareException()
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->share($testClass);

        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage('Cannot alias class stdclass to Auryn\Test\SomeOtherClass because it is currently shared');
        $this->expectExceptionCode(\Auryn\Injector::E_SHARED_CANNOT_ALIAS);
        $injector->alias('StdClass', 'Auryn\Test\SomeOtherClass');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Auryn\Test\HasNonPublicConstructor');
        $this->expectExceptionCode(\Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector = new Injector();
        $injector->make('Auryn\Test\HasNonPublicConstructor');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Auryn\Test\HasNonPublicConstructorWithArgs');
        $this->expectExceptionCode(\Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector = new Injector();
        $injector->make('Auryn\Test\HasNonPublicConstructorWithArgs');
    }

    public function testMakeExecutableFailsOnNonExistentFunction()
    {
        $injector = new Injector();
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('nonExistentFunction');
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);


        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod()
    {
        $injector = new Injector();
        $object = new \StdClass();
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage("[object(stdClass), 'nonExistentMethod']");
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod()
    {
        $injector = new Injector();
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage("StdClass::nonExistentMethod");
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);

        $injector->buildExecutable(array('StdClass', 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnClassWithoutInvoke()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('Invalid invokable: callable or provisional string required');
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);

        $injector = new Injector();
        $object = new \StdClass();
        $injector->buildExecutable($object);
    }

    public function testBadAliasFirstArg()
    {
        $injector = new Injector;

        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage(Injector::M_NON_EMPTY_STRING_ALIAS);
        $this->expectExceptionCode(\Auryn\Injector::E_NON_EMPTY_STRING_ALIAS);

        $injector->alias('', 'Auryn\Test\DepImplementation');
    }

    public function testBadAliasSecondArg()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepInterface');


        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage('Invalid alias: non-empty string required at arguments 1 and 2');
        $this->expectExceptionCode(\Auryn\Injector::E_NON_EMPTY_STRING_ALIAS);

        $injector->alias('Auryn\Test\DepInterface', '');
    }

    public function testShareNewAlias()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepImplementation');
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->define('Auryn\Test\SimpleNoTypeClass', array(':arg' => 'tested'));
        $testClass = $injector->make('Auryn\Test\SimpleNoTypeClass');
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->share('\StdClass');
        $classA = $injector->make('StdClass');
        $classA->tested = false;
        $classB = $injector->make('\StdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate()
    {
        $injector = new Injector();
        $injector->prepare('\StdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $injector = new Injector();
        $injector->prepare('Auryn\Test\SomeInterface', function ($obj, $injector) {
            $obj->testProp = 42;
        });
        $obj = $injector->make('Auryn\Test\PreparesImplementationTest');

        $this->assertSame(42, $obj->testProp);
    }



    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     */
    public function testCustomDefinitionNotPassedThrough()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DependencyWithDefinedParam');

        $this->expectException(\Auryn\InjectionException::class);
        // TODO - why does this message end with double-colon?
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Auryn\Test\DependencyWithDefinedParam::__construct() declared in Auryn\Test\DependencyWithDefinedParam::');
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);

        $injector->make('Auryn\Test\RequiresDependencyWithDefinedParam', array(':foo' => 5));
    }

    public function testDelegationFunction()
    {
        $injector = new Injector();
        $injector->delegate('Auryn\Test\TestDelegationSimple', 'Auryn\Test\createTestDelegationSimple');
        $obj = $injector->make('Auryn\Test\TestDelegationSimple');
        $this->assertInstanceOf('Auryn\Test\TestDelegationSimple', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency()
    {
        $injector = new Injector();
        $injector->delegate(
            'Auryn\Test\TestDelegationDependency',
            'Auryn\Test\createTestDelegationDependency'
        );
        $obj = $injector->make('Auryn\Test\TestDelegationDependency');
        $this->assertInstanceOf('Auryn\Test\TestDelegationDependency', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing()
    {
        $injector = new Injector();
        $injector->alias('Auryn\Test\BaseExecutableClass', 'Auryn\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('Auryn\Test\BaseExecutableClass', 'foo'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic()
    {
        $injector = new Injector();
        $injector->alias('Auryn\Test\BaseExecutableClass', 'Auryn\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('Auryn\Test\BaseExecutableClass', 'bar'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     * @throws \Auryn\ConfigException
     */
    public function testDelegateClosure()
    {
        $delegateClosure = \Auryn\Test\getDelegateClosureInGlobalScope();
        $injector = new Injector();
        $injector->delegate('Auryn\Test\DelegateClosureInGlobalScope', $delegateClosure);
        $injector->make('Auryn\Test\DelegateClosureInGlobalScope');
    }

    public function testCloningWithServiceLocator()
    {
        $injector = new Injector();
        $injector->share($injector);
        $instance = $injector->make('Auryn\Test\CloneTest');
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make('Auryn\Test\CloneTest');
    }

    public function testAbstractExecute()
    {
        $injector = new Injector();

        $fn = function () {
            return new \Auryn\Test\ConcreteExexcuteTest();
        };

        $injector->delegate('Auryn\Test\AbstractExecuteTest', $fn);
        $result = $injector->execute(array('Auryn\Test\AbstractExecuteTest', 'process'));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake()
    {
        $injector = new Injector();
        try {
            $injector->make('Auryn\Test\DependencyChainTest');
        } catch (\Auryn\InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertEquals('auryn\test\dependencychaintest', $chain[0]);
            $this->assertEquals('auryn\test\depinterface', $chain[1]);
        }
    }

    public function testInspectShares()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\SomeClassName');

        $inspection = $injector->inspect('Auryn\Test\SomeClassName', Injector::I_SHARES);
        $this->assertArrayHasKey('auryn\test\someclassname', $inspection[Injector::I_SHARES]);
    }

    public function testInspectAll()
    {
        $injector = new Injector();

        // Injector::I_BINDINGS
        $injector->define('Auryn\Test\DependencyWithDefinedParam', array(':arg' => 42));

        // Injector::I_DELEGATES
        $injector->delegate('Auryn\Test\MadeByDelegate', 'Auryn\Test\CallableDelegateClassTest');

        // Injector::I_PREPARES
        $injector->prepare('Auryn\Test\MadeByDelegate', function ($c) {});

        // Injector::I_ALIASES
        $injector->alias('i', 'Auryn\Injector');

        // Injector::I_SHARES
        $injector->share('Auryn\Injector');

        $all = $injector->inspect();
        $some = $injector->inspect('Auryn\Test\MadeByDelegate');

        $this->assertCount(5, array_filter($all));
        $this->assertCount(2, array_filter($some));
    }

    public function testDelegationDoesntMakeObject()
    {
        $delegate = function () {
            return null;
        };
        $injector = new Injector();
        $injector->delegate('Auryn\Test\SomeClassName', $delegate);

        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('Making auryn\test\someclassname did not result in an object, instead result is of type \'NULL\'');
        $this->expectExceptionCode(\Auryn\Injector::E_MAKING_FAILED);

        $injector->make('Auryn\Test\SomeClassName');
    }

    public function testDelegationDoesntMakeObjectMakesString()
    {
        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector();
        $injector->delegate('Auryn\Test\SomeClassName', $delegate);

        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('Making auryn\test\someclassname did not result in an object, instead result is of type \'string\'');
        $this->expectExceptionCode(\Auryn\Injector::E_MAKING_FAILED);

        $injector->make('Auryn\Test\SomeClassName');
    }

    public function testPrepareInvalidCallable()
    {
        $injector = new Injector;
        $invalidCallable = 'This_does_not_exist';
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage($invalidCallable);

        $injector->prepare("StdClass", $invalidCallable);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("Auryn\Test\SomeInterface", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("Auryn\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("Auryn\Test\SomeImplementation", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("Auryn\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks() {

        $injector = new Injector;
        try {
            $injector->define('Auryn\Test\ParentWithConstructor', array(':foo' => 'parent'));
            $injector->define('Auryn\Test\ChildWithoutConstructor', array(':foo' => 'child'));

            $injector->share('Auryn\Test\ParentWithConstructor');
            $injector->share('Auryn\Test\ChildWithoutConstructor');

            $child = $injector->make('Auryn\Test\ChildWithoutConstructor');
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make('Auryn\Test\ParentWithConstructor');
            $this->assertEquals('parent', $parent->foo);
        }
        catch (\Auryn\InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail("Auryn failed to locate the ");
        }
    }

    public function testChildWithoutConstructorMissingParam()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\ParentWithConstructor', array(':foo' => 'parent'));


        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Auryn\Test\ChildWithoutConstructor::__construct() declared in Auryn\Test\ParentWithConstructor');

        $injector->make('Auryn\Test\ChildWithoutConstructor');
    }

    public function testInstanceClosureDelegates()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\DelegatingInstanceA', function (DelegateA $d) {
            return new \Auryn\Test\DelegatingInstanceA($d);
        });
        $injector->delegate('Auryn\Test\DelegatingInstanceB', function (DelegateB $d) {
            return new \Auryn\Test\DelegatingInstanceB($d);
        });

        $a = $injector->make('Auryn\Test\DelegatingInstanceA');
        $b = $injector->make('Auryn\Test\DelegatingInstanceB');

        $this->assertInstanceOf('Auryn\Test\DelegateA', $a->a);
        $this->assertInstanceOf('Auryn\Test\DelegateB', $b->b);
    }


    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException()
    {
        $injector = new Injector;

        try {
            $injector->make('Auryn\Test\ThrowsExceptionInConstructor');
        } catch (\Exception $e) {
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exception in constructor');

        $injector->make('Auryn\Test\ThrowsExceptionInConstructor');
    }

    public function testProvidesExtensionsOfArrayMap()
    {
        $injector = new Injector;
        $obj = $injector->make('\Auryn\Test\ExtendedExtendedArrayObject');

        $this->assertInstanceOf('\ArrayObject', $obj);
    }

    // interpret the param as an invokable delegate
    public function testMakeWithParameter_delegate()
    {
        $value = 'testMakeWithParameter_delegate';
        $closure_was_called = false;
        $fn = function () use (&$closure_was_called, $value) {
            $closure_was_called = true;
            return \Auryn\Test\PrefixDelegateTestDependency::create($value);
        };

        $injector = new Injector;
        $object = $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_DELEGATE . 'b' => $fn]
        );
        $this->assertInstanceOf(
            \Auryn\Test\PrefixDelegateTest::class,
            $object
        );
        $this->assertTrue($closure_was_called);
        $this->assertSame($value, $object->getB()->getValue());
    }

    // // interpret the param as a raw value to be injected
    public function testMakeWithParameter_raw()
    {
        $value = 'testMakeWithParameter_raw';
        $injector = new Injector;
        $object = $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_RAW . 'b' => \Auryn\Test\PrefixDelegateTestDependency::create($value)]
        );
        $this->assertInstanceOf(
            \Auryn\Test\PrefixDelegateTest::class,
            $object
        );

        $this->assertSame($value, $object->getB()->getValue());
    }

    // interpret the param as a class definition
    public function testMakeWithParameter_define()
    {
        $injector = new Injector;
        $object = $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_DEFINE . 'b' => [\Auryn\Test\PrefixDelegateTestDependencyInstantiable::class, []]]
        );
        $this->assertInstanceOf(
            \Auryn\Test\PrefixDelegateTest::class,
            $object
        );

        $this->assertSame('this is the child class', $object->getB()->getValue());
    }

    /**
     * interpret the param as a class definition
     * @return void
     * @throws \Auryn\InjectionException
     */
    public function testMakeWithParameter_define_uses_info()
    {
        $message = "great success";
        $injector = new Injector;

        $params = [
            \Auryn\Test\PrefixDefineDependency::class,
            [Injector::A_RAW . 'message' => $message]
        ];

        $object = $injector->make(
            \Auryn\Test\PrefixDefineTest::class,
            [Injector::A_DEFINE . 'pdd' => $params]
        );

        $this->assertInstanceOf(
            \Auryn\Test\PrefixDefineTest::class,
            $object
        );

        $dependency = $object->getPdd();
        $this->assertInstanceOf(\Auryn\Test\PrefixDefineDependency::class, $dependency);
        $this->assertSame($message, $dependency->message);
    }

    public function testIndexedArrayElementOverridesNamedPostion()
    {
        $message = "This is used.";
        $params = [
           Injector::A_RAW . 'message' => "This is not used",
           0 => $message
        ];
        $injector = new Injector;
        $object = $injector->make(
            \Auryn\Test\PrefixDefineDependency::class,
            $params
        );

        $this->assertInstanceOf(
            \Auryn\Test\PrefixDefineDependency::class,
            $object
        );

        $this->assertSame($message, $object->message);
    }

    public function testMakeWithParameter_define_errors_not_array()
    {
        $injector = new Injector;
        $this->expectExceptionCode(Injector::E_INVALID_DEFINE_ARGUMENT_NOT_ARRAY);
        $this->expectExceptionMessageMatchesTemplateString(
            Injector::M_INVALID_DEFINE_ARGUMENT_NOT_ARRAY
        );
        $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_DEFINE . 'b' => 'this is not an array']
        );
    }

    public function testMakeWithParameter_define_errors_bad_indexed_array_empty()
    {
        $injector = new Injector;
        $this->expectExceptionCode(Injector::E_INVALID_DEFINE_ARGUMENT_BAD_KEYS);
        $this->expectExceptionMessageMatchesTemplateString(
            Injector::M_INVALID_DEFINE_ARGUMENT_BAD_KEYS
        );

        $this->expectExceptionMessageContains("array key 0 not set array key 1 not set");

        $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_DEFINE . 'b' => []]
        );
    }

    public function testMakeWithParameter_define_errors_bad_indexed_array_wrong_position()
    {
        $injector = new Injector;
        $this->expectExceptionCode(Injector::E_INVALID_DEFINE_ARGUMENT_BAD_KEYS);
        $this->expectExceptionMessageMatchesTemplateString(
            Injector::M_INVALID_DEFINE_ARGUMENT_BAD_KEYS
        );

        $this->expectExceptionMessageContains("array key 1 not set");

        $params = [
            \Auryn\Test\PrefixDelegateTestDependencyInstantiable::class,
            2 => []
        ];
        $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_DEFINE . 'b' => $params]
        );
    }

    public function testMakeWithParameter_delegate_errors_not_callable()
    {
        $injector = new Injector;
        $this->expectExceptionCode(Injector::E_INVOKABLE);
        $this->expectExceptionMessage(Injector::M_INVOKABLE);
        $injector->make(
            \Auryn\Test\PrefixDelegateTest::class,
            [Injector::A_DELEGATE . 'b' => 'this is not callable']
        );
    }

    public function testDoubleShareClassThrows()
    {
        $injector = new Injector;
        $injector->share(new \StdClass);

        $this->expectExceptionCode(Injector::E_DOUBLE_SHARE);
        $this->expectExceptionMessageMatchesTemplateString(Injector::M_DOUBLE_SHARE);
        $this->expectExceptionMessageContains('stdclass');

        $injector->share(new \StdClass);
    }


    /**
     * This test is duplication of other tests. It is present to check
     * that the behaviour of three different ways of params being null-ish
     * are consistent.
     *
     * @requires PHP 8.0
     */
    public function testNullConsistency()
    {
        require_once __DIR__ . "/fixtures_8_0.php";

        $injector = new Injector;
        $obj = $injector->make(\NullableDependency::class);
        $this->assertInstanceOf(\NullableDependency::class, $obj);
        $this->assertNull($obj->instance);

        $obj = $injector->make(\UnionNullDependency::class);
        $this->assertInstanceOf(\UnionNullDependency::class, $obj);
        $this->assertNull($obj->instance);

        $obj = $injector->make(\DefaultNullDependency::class);
        $this->assertInstanceOf(\DefaultNullDependency::class, $obj);
        $this->assertNull($obj->instance);
    }

    /**
     * @requires PHP 8.1
     */
    public function testNewInIntializer()
    {
        require_once __DIR__ . "/fixtures_8_1.php";

        $injector = new Injector;
        $obj = $injector->make(\NewInInitializer::class);

        $this->assertInstanceOf(\NewInInitializer::class, $obj);
        $this->assertInstanceOf(\NewInInitializerDependency::class, $obj->instance);
    }
}
