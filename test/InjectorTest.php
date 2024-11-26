<?php

namespace Auryn;

use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
{
    public function testArrayTypehintDoesNotEvaluatesAsClass(): void
    {
        $injector = new Injector();
        $injector->defineParam('parameter', []);
        $injector->execute('Auryn\hasArrayDependency');
        $this->assertTrue(true);
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency(): void
    {
        $injector = new Injector();
        $this->assertEquals(
            new TestNeedsDep(new TestDependency()),
            $injector->make(TestNeedsDep::class)
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor(): void
    {
        $injector = new Injector();
        $this->assertEquals(new TestNoConstructor(), $injector->make(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        $injector = new Injector();
        $injector->alias(DepInterface::class, DepImplementation::class);
        $this->assertEquals(new DepImplementation(), $injector->make(DepInterface::class));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Auryn\DepInterface');
        $this->expectExceptionCode(Injector::E_NEEDS_DEFINITION);

        $injector = new Injector();
        $injector->make(DepInterface::class);
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Auryn\DepInterface');
        $this->expectExceptionCode(Injector::E_NEEDS_DEFINITION);

        $injector = new Injector();
        $injector->make(RequiresInterface::class);
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias(): void
    {
        $injector = new Injector();
        $injector->alias(DepInterface::class, DepImplementation::class);
        $obj = $injector->make(RequiresInterface::class);
        $this->assertInstanceOf(RequiresInterface::class, $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined(): void
    {
        $injector = new Injector();
        $nullCtorParamObj = $injector->make(ProvTestNoDefinitionNullDefaultClass::class);
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass(), $nullCtorParamObj);
        $this->assertNull($nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable(): void
    {
        $injector = new Injector();
        $injector->define(RequiresInterface::class, array('dep' => DepImplementation::class));
        $injector->share(RequiresInterface::class);
        $injected = $injector->make(RequiresInterface::class);

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make(RequiresInterface::class);
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure(): void
    {
        $this->expectException(InjectorException::class);
        if (PHP_VERSION_ID >= 80000) {
            $this->expectExceptionMessage('Could not make ClassThatDoesntExist: Class "ClassThatDoesntExist" does not exist');
        } else {
            $this->expectExceptionMessage('Could not make ClassThatDoesntExist: Class ClassThatDoesntExist does not exist');
        }

        $injector = new Injector();
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified(): void
    {
        $injector = new Injector();
        $injector->define(TestNeedsDep::class, array('testDep'=>TestDependency::class));
        $injected = $injector->make(TestNeedsDep::class, array('testDep'=>TestDependency2::class));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions(): void
    {
        $injector = new Injector();
        $injector->define(InjectorTestChildClass::class, array(':arg1'=>'First argument', ':arg2'=>'Second argument'));
        $injected = $injector->make(InjectorTestChildClass::class, array(':arg1'=>'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance(): void
    {
        $injector = new Injector();
        $injector->share(TestDependency::class);
        $injector->make(TestDependency::class);
        $this->assertTrue(true);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps(): void
    {
        $injector = new Injector();
        $obj = $injector->make(TestMultiDepsWithCtor::class, array('val1'=>TestDependency::class));
        $this->assertInstanceOf(TestMultiDepsWithCtor::class, $obj);

        $obj = $injector->make(
            NoTypehintNoDefaultConstructorClass::class,
            array('val1'=>TestDependency::class)
        );
        $this->assertInstanceOf(NoTypehintNoDefaultConstructorClass::class, $obj);
        $this->assertNull($obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics(): void
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector();
        $obj = $injector->make(
            NoTypehintNoDefaultConstructorVariadicClass::class,
            array('val1'=>TestDependency::class)
        );
        $this->assertInstanceOf(NoTypehintNoDefaultConstructorVariadicClass::class, $obj);
        $this->assertEquals(array(), $obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithTypeHint(): void
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector();
        $obj = $injector->make(
            TypehintNoDefaultConstructorVariadicClass::class,
            array('arg'=>TestDependency::class)
        );
        $this->assertInstanceOf(TypehintNoDefaultConstructorVariadicClass::class, $obj);
        $this->assertIsArray($obj->testParam);
        $this->assertInstanceOf(TestDependency::class, $obj->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Auryn\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in Auryn\InjectorTestCtorParamWithNoTypehintOrDefault::');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector = new Injector();
        $injector->make(InjectorTestCtorParamWithNoTypehintOrDefault::class);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Auryn\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in Auryn\InjectorTestCtorParamWithNoTypehintOrDefault::');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector = new Injector();
        $injector->alias(TestNoExplicitDefine::class, InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $injector->make(InjectorTestCtorParamWithNoTypehintOrDefaultDependent::class);
    }

    /**
     * @TODO
     */
    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Injection definition required for interface Auryn\DepInterface');

        $injector = new Injector();
        $injector->make(RequiresInterface::class);
    }

    public function testTypelessDefineForDependency(): void
    {
        $thumbnailSize = 128;
        $injector = new Injector();
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make(RequiresDependencyWithTypelessParameters::class);
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(), 'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency(): void
    {
        $injector = new Injector();
        $injector->defineParam('val', 42);

        $injector->alias(TestNoExplicitDefine::class, ProviderTestCtorParamWithNoTypehintOrDefault::class);
        $obj = $injector->make(ProviderTestCtorParamWithNoTypehintOrDefaultDependent::class);
        $this->assertInstanceOf(ProviderTestCtorParamWithNoTypehintOrDefaultDependent::class, $obj);
    }

    public function testMakeInstanceInjectsRawParametersDirectly(): void
    {
        $injector = new Injector();
        $injector->define(InjectorTestRawCtorParams::class, array(
            ':string' => 'string',
            ':obj' => new \StdClass(),
            ':int' => 42,
            ':array' => array(),
            ':float' => 9.3,
            ':bool' => true,
            ':null' => null,
        ));

        $obj = $injector->make(InjectorTestRawCtorParams::class);
        $this->assertTrue(is_string($obj->string));
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertIsInt($obj->int);
        $this->assertIsArray($obj->array);
        $this->assertIsFloat($obj->float);
        $this->assertIsBool($obj->bool);
        $this->assertNull($obj->null);
    }

    public function testMakeInstanceThrowsExceptionWhenDelegateDoes(): void
    {
        $this->expectException(\Exception::class);

        $injector = new Injector();

        $callable = $this->createMock(CallableMock::class);

        $injector->delegate('TestDependency', $callable);

        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception()));

        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses(): void
    {
        $injector = new Injector();
        $injector->make(SomeClassName::class);
        $this->assertTrue(true);
    }

    public function testMakeInstanceDelegate(): void
    {
        $injector= new Injector();

        $callable = $this->createMock(CallableMock::class);

        $callable->expects($this->once())
            ->method('__invoke')
            ->willReturn(new TestDependency());

        $injector->delegate(TestDependency::class, $callable);

        $obj = $injector->make(TestDependency::class);

        $this->assertInstanceOf(TestDependency::class, $obj);
    }

    public function testMakeInstanceWithStringDelegate(): void
    {
        $injector= new Injector();
        $injector->delegate('StdClass', StringStdClassDelegateMock::class);
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'StringDelegateWithNoInvokeMethod'");

        $injector = new Injector();
        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'SomeClassThatDefinitelyDoesNotExistForReal'");

        $injector = new Injector();
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Auryn\DepInterface');

        $injector = new Injector();
        $injector->make(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition(): void
    {
        $injector = new Injector();
        $definition = array('dep' => DepImplementation::class);
        $injector->define(RequiresInterface::class, $definition);
        $this->assertInstanceOf(RequiresInterface::class, $injector->make(RequiresInterface::class));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance(): void
    {
        $injector = new Injector();
        $testShare = new \StdClass();
        $testShare->test = 42;

        $this->assertInstanceOf(Injector::class, $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter(): void
    {
        $injector = new Injector();
        $this->assertInstanceOf(Injector::class, $injector->share('SomeClass'));
    }

    public function testShareThrowsExceptionOnInvalidArgument(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Auryn\Injector::share() requires a string class name or object instance at Argument 1; integer specified');

        $injector = new Injector();
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance(): void
    {
        $injector = new Injector();
        $this->assertInstanceOf(Injector::class, $injector->alias('DepInterface', DepImplementation::class));
    }

    public static function provideInvalidDelegates(): array
    {
        return array(
            array(new \StdClass()),
            array(42),
            array(true)
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2');

        $injector = new Injector();
        $injector->delegate(TestDependency::class, $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString(): void
    {
        $injector = new Injector();
        $injector->delegate(MadeByDelegate::class, CallableDelegateClassTest::class);
        $this->assertInstanceof(MadeByDelegate::class, $injector->make(MadeByDelegate::class));
    }

    public function testDelegateInstantiatesCallableClassArray(): void
    {
        $injector = new Injector();
        $injector->delegate(MadeByDelegate::class, array(CallableDelegateClassTest::class, '__invoke'));
        $this->assertInstanceof(MadeByDelegate::class, $injector->make(MadeByDelegate::class));
    }

    public function testUnknownDelegationFunction(): void
    {
        $injector = new Injector();
        try {
            $injector->delegate(DelegatableInterface::class, 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (InjectorException $ie) {
            $this->assertStringContainsString('FunctionWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod(): void
    {
        $injector = new Injector();
        try {
            $injector->delegate(DelegatableInterface::class, array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (InjectorException $ie) {
            $this->assertStringContainsString('stdClass', $ie->getMessage());
            $this->assertStringContainsString('methodWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult): void
    {
        $injector = new Injector();
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
    }

    public static function provideExecutionExpectations(): array
    {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke = array(ExecuteClassNoDeps::class, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassNoDeps(), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke = array(ExecuteClassDeps::class, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassDeps(new TestDependency()), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke = array(ExecuteClassDepsWithMethodDeps::class, 'execute');
        $args = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke = array(ExecuteClassStaticMethod::class, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassStaticMethod(), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke = array(ExecuteClassRelativeStaticMethod::class, 'parent::execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\testExecuteFunction';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke = new ExecuteClassInvokable();
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke = ExecuteClassInvokable::class;
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\ExecuteClassNoDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\ExecuteClassDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\testExecuteFunctionWithArg';
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

            $object = new ReturnsCallable('new value');
            $args = array();
            $toInvoke = $object->getCallable();
            $expectedResult = 'new value';
            $return[] = array($toInvoke, $args, $expectedResult);
        }
        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function testStaticStringInvokableWithArgument(): void
    {
        $injector = new Injector();
        $invokable = $injector->buildExecutable('Auryn\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation(): void
    {
        $injector = new Injector();
        $injector->delegate(DelegatableInterface::class, ImplementsInterfaceFactory::class);
        $requiresDelegatedInterface = $injector->make(RequiresDelegatedInterface::class);
        $requiresDelegatedInterface->foo();
        $this->assertTrue(true);
    }

    public function testMissingAlias(): void
    {
        $this->expectException(InjectorException::class);
        if (PHP_VERSION_ID >= 80000) {
            $this->expectExceptionMessage('Could not make Auryn\TypoInTypehint: Class "Auryn\TypoInTypehint" does not exist');
        } else {
            $this->expectExceptionMessage('Could not make Auryn\TestMissingDependency: Class Auryn\TypoInTypehint does not exist');
        }


        $injector = new Injector();
        $testClass = $injector->make(TestMissingDependency::class);
    }

    public function testAliasingConcreteClasses(): void
    {
        $injector = new Injector();
        $injector->alias(ConcreteClass1::class, ConcreteClass2::class);
        $obj = $injector->make(ConcreteClass1::class);
        $this->assertInstanceOf(ConcreteClass2::class, $obj);
    }

    public function testSharedByAliasedInterfaceName(): void
    {
        $injector = new Injector();
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $injector->share(SharedAliasedInterface::class);
        $class = $injector->make(SharedAliasedInterface::class);
        $class2 = $injector->make(SharedAliasedInterface::class);
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName(): void
    {
        $injector = new Injector();
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $injector->alias(SharedAliasedInterface::class, NotSharedClass::class);
        $injector->share(SharedClass::class);
        $class = $injector->make(SharedAliasedInterface::class);
        $class2 = $injector->make(SharedAliasedInterface::class);

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder(): void
    {
        $injector = new Injector();
        $injector->share(SharedAliasedInterface::class);
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $class = $injector->make(SharedAliasedInterface::class);
        $class2 = $injector->make(SharedAliasedInterface::class);
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter(): void
    {
        $injector = new Injector();
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $injector->share(SharedAliasedInterface::class);
        $sharedClass = $injector->make(SharedAliasedInterface::class);
        $childClass = $injector->make(ClassWithAliasAsParameter::class);
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance(): void
    {
        $injector = new Injector();
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $sharedClass = $injector->make(SharedAliasedInterface::class);
        $injector->share($sharedClass);
        $childClass = $injector->make(ClassWithAliasAsParameter::class);
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance(): void
    {
        $injector = new Injector();
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor(): void
    {
        $injector = new Injector();

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make(TestNeedsDepWithProtCons::class);

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared(): void
    {
        $injector = new Injector();
        $injector->share(ClassInnerB::class);
        $innerDep = $injector->make(ClassInnerB::class);
        $inner = $injector->make(ClassInnerA::class);
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make(ClassOuter::class);
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo(): void
    {
        $injector = new Injector();
        $obj = $injector->make(ClassOuter::class);
        $this->assertInstanceOf(ClassOuter::class, $obj);
        $this->assertInstanceOf(ClassInnerA::class, $obj->dep);
        $this->assertInstanceOf(ClassInnerB::class, $obj->dep->dep);
    }

    public static function provideCyclicDependencies(): array
    {
        return array(
            RecursiveClassA::class => array(RecursiveClassA::class),
            RecursiveClassB::class => array(RecursiveClassB::class),
            RecursiveClassC::class => array(RecursiveClassC::class),
            RecursiveClass1::class => array(RecursiveClass1::class),
            RecursiveClass2::class => array(RecursiveClass2::class),
            DependsOnCyclic::class => array(DependsOnCyclic::class),
        );
    }

    /**
     * @dataProvider provideCyclicDependencies
     */
    public function testCyclicDependencies($class): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionCode(Injector::E_CYCLIC_DEPENDENCY);

        $injector = new Injector();
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault(): void
    {
        $injector = new Injector();
        $class = $injector->make(NonConcreteDependencyWithDefaultValue::class);
        $this->assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias(): void
    {
        $injector = new Injector();
        $injector->alias(
            DelegatableInterface::class,
            ImplementsInterface::class
        );
        $class = $injector->make(NonConcreteDependencyWithDefaultValue::class);
        $this->assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        $this->assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation(): void
    {
        $injector = new Injector();
        $injector->delegate(DelegatableInterface::class, ImplementsInterfaceFactory::class);
        $class = $injector->make(NonConcreteDependencyWithDefaultValue::class);
        $this->assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        $this->assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare(): void
    {
        $injector = new Injector();
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make(ConcreteDependencyWithDefaultValue::class);
        $this->assertNull($instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $instance = new \StdClass();
        $injector->share($instance);
        $instance = $injector->make(ConcreteDependencyWithDefaultValue::class);
        $this->assertInstanceOf('StdClass', $instance->dependency);
    }

    public function testShareAfterAliasException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Cannot share class stdclass because it is currently aliased to Auryn\SomeOtherClass');
        $this->expectExceptionCode(Injector::E_ALIASED_CANNOT_SHARE);

        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->alias('StdClass', SomeOtherClass::class);
        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed(): void
    {
        $injector = new Injector();
        $testClass = new DepImplementation();
        $injector->alias(DepInterface::class, DepImplementation::class);
        $injector->share($testClass);
        $obj = $injector->make(DepInterface::class);
        $this->assertInstanceOf(DepImplementation::class, $obj);
    }

    public function testAliasAfterShareByStringAllowed(): void
    {
        $injector = new Injector();
        $injector->share(DepInterface::class);
        $injector->alias(DepInterface::class, DepImplementation::class);
        $obj = $injector->make(DepInterface::class);
        $obj2 = $injector->make(DepInterface::class);
        $this->assertInstanceOf(DepImplementation::class, $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed(): void
    {
        $injector = new Injector();
        $injector->share(DepImplementation::class);
        $injector->alias(DepInterface::class, DepImplementation::class);
        $obj = $injector->make(DepInterface::class);
        $obj2 = $injector->make(DepInterface::class);
        $this->assertInstanceOf(DepImplementation::class, $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Cannot alias class stdclass to Auryn\SomeOtherClass because it is currently shared');
        $this->expectExceptionCode(Injector::E_SHARED_CANNOT_ALIAS);

        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->share($testClass);
        $injector->alias('StdClass', SomeOtherClass::class);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Auryn\HasNonPublicConstructor');
        $this->expectExceptionCode(Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector = new Injector();
        $injector->make(HasNonPublicConstructor::class);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Auryn\HasNonPublicConstructorWithArgs');
        $this->expectExceptionCode(Injector::E_NON_PUBLIC_CONSTRUCTOR);


        $injector = new Injector();
        $injector->make(HasNonPublicConstructorWithArgs::class);
    }

    public function testMakeExecutableFailsOnNonExistentFunction(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('nonExistentFunction');
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector = new Injector();
        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage("[object(stdClass), 'nonExistentMethod']");
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector = new Injector();
        $object = new \StdClass();
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('StdClass::nonExistentMethod');
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector = new Injector();
        $injector->buildExecutable(array('StdClass', 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnClassWithoutInvoke(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Invalid invokable: callable or provisional string required');
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector = new Injector();
        $object = new \StdClass();
        $injector->buildExecutable($object);
    }

    public function testBadAlias(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid alias: non-empty string required at arguments 1 and 2');
        $this->expectExceptionCode(Injector::E_NON_EMPTY_STRING_ALIAS);

        $injector = new Injector();
        $injector->share(DepInterface::class);
        $injector->alias(DepInterface::class, '');
    }

    public function testShareNewAlias(): void
    {
        $injector = new Injector();
        $injector->share(DepImplementation::class);
        $injector->alias(DepInterface::class, DepImplementation::class);
        $this->assertTrue(true);
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash(): void
    {
        $injector = new Injector();
        $injector->define(SimpleNoTypehintClass::class, array(':arg' => 'tested'));
        $testClass = $injector->make(SimpleNoTypehintClass::class);
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash(): void
    {
        $injector = new Injector();
        $injector->share('\StdClass');
        $classA = $injector->make('StdClass');
        $classA->tested = false;
        $classB = $injector->make('\StdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate(): void
    {
        $injector = new Injector();
        $injector->prepare('\StdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate(): void
    {
        $injector = new Injector();
        $injector->prepare(SomeInterface::class, function ($obj, $injector) {
            $obj->testProp = 42;
        });
        $obj = $injector->make(PreparesImplementationTest::class);

        $this->assertSame(42, $obj->testProp);
    }



    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     */
    public function testCustomDefinitionNotPassedThrough(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Auryn\DependencyWithDefinedParam::__construct() declared in Auryn\DependencyWithDefinedParam::');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector = new Injector();
        $injector->share(DependencyWithDefinedParam::class);
        $injector->make(RequiresDependencyWithDefinedParam::class, array(':foo' => 5));
    }

    public function testDelegationFunction(): void
    {
        $injector = new Injector();
        $injector->delegate(TestDelegationSimple::class, 'Auryn\createTestDelegationSimple');
        $obj = $injector->make(TestDelegationSimple::class);
        $this->assertInstanceOf(TestDelegationSimple::class, $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency(): void
    {
        $injector = new Injector();
        $injector->delegate(
            TestDelegationDependency::class,
            'Auryn\createTestDelegationDependency'
        );
        $obj = $injector->make(TestDelegationDependency::class);
        $this->assertInstanceOf(TestDelegationDependency::class, $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing(): void
    {
        $injector = new Injector();
        $injector->alias(BaseExecutableClass::class, ExtendsExecutableClass::class);
        $result = $injector->execute(array(BaseExecutableClass::class, 'foo'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic(): void
    {
        $injector = new Injector();
        $injector->alias(BaseExecutableClass::class, ExtendsExecutableClass::class);
        $result = $injector->execute(array(BaseExecutableClass::class, 'bar'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     * @throws \Auryn\ConfigException
     */
    public function testDelegateClosure(): void
    {
        $delegateClosure = \Auryn\getDelegateClosureInGlobalScope();
        $injector = new Injector();
        $injector->delegate(DelegateClosureInGlobalScope::class, $delegateClosure);
        $injector->make(DelegateClosureInGlobalScope::class);
        $this->assertTrue(true);
    }

    public function testCloningWithServiceLocator(): void
    {
        $injector = new Injector();
        $injector->share($injector);
        $instance = $injector->make(CloneTest::class);
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make(CloneTest::class);
        $this->assertInstanceOf(CloneTest::class, $newInstance);
    }

    public function testAbstractExecute(): void
    {
        $injector = new Injector();

        $fn = function () {
            return new \Auryn\ConcreteExexcuteTest();
        };

        $injector->delegate(AbstractExecuteTest::class, $fn);
        $result = $injector->execute(array(AbstractExecuteTest::class, 'process'));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake(): void
    {
        $injector = new Injector();
        try {
            $injector->make(DependencyChainTest::class);
        } catch (InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertStringContainsStringIgnoringCase(DependencyChainTest::class, $chain[0]);
            $this->assertStringContainsStringIgnoringCase(depinterface::class, $chain[1]);
        }
    }

    public function testInspectShares(): void
    {
        $injector = new Injector();
        $injector->share(SomeClassName::class);

        $inspection = $injector->inspect(SomeClassName::class, Injector::I_SHARES);
        $this->assertArrayHasKey('auryn\someclassname', $inspection[Injector::I_SHARES]);
    }

    public function testInspectAll(): void
    {
        $injector = new Injector();

        // Injector::I_BINDINGS
        $injector->define(DependencyWithDefinedParam::class, array(':arg' => 42));

        // Injector::I_DELEGATES
        $injector->delegate(MadeByDelegate::class, CallableDelegateClassTest::class);

        // Injector::I_PREPARES
        $injector->prepare(MadeByDelegate::class, function ($c) {
        });

        // Injector::I_ALIASES
        $injector->alias('i', Injector::class);

        // Injector::I_SHARES
        $injector->share(Injector::class);

        $all = $injector->inspect();
        $some = $injector->inspect(MadeByDelegate::class);

        $this->assertCount(5, array_filter($all));
        $this->assertCount(2, array_filter($some));
    }

    public function testDelegationDoesntMakeObject(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage("Making auryn\someclassname did not result in an object, instead result is of type 'NULL'");
        $this->expectExceptionCode(Injector::E_MAKING_FAILED);

        $delegate = function () {
            return null;
        };
        $injector = new Injector();
        $injector->delegate(SomeClassName::class, $delegate);
        $injector->make(SomeClassName::class);
    }

    public function testDelegationDoesntMakeObjectMakesString(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage("Making auryn\someclassname did not result in an object, instead result is of type 'string'");
        $this->expectExceptionCode(Injector::E_MAKING_FAILED);

        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector();
        $injector->delegate(SomeClassName::class, $delegate);
        $injector->make(SomeClassName::class);
    }

    public function testPrepareInvalidCallable(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('This_does_not_exist');

        $injector = new Injector();
        $invalidCallable = 'This_does_not_exist';
        $injector->prepare("StdClass", $invalidCallable);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType(): void
    {
        $injector = new Injector();
        $expected = new SomeImplementation(); // <-- implements SomeInterface
        $injector->prepare(SomeInterface::class, function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make(SomeImplementation::class);
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType(): void
    {
        $injector = new Injector();
        $expected = new SomeImplementation(); // <-- implements SomeInterface
        $injector->prepare(SomeImplementation::class, function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make(SomeImplementation::class);
        $this->assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks(): void
    {
        $injector = new Injector();
        try {
            $injector->define(ParentWithConstructor::class, array(':foo' => 'parent'));
            $injector->define(ChildWithoutConstructor::class, array(':foo' => 'child'));

            $injector->share(ParentWithConstructor::class);
            $injector->share(ChildWithoutConstructor::class);

            $child = $injector->make(ChildWithoutConstructor::class);
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make(ParentWithConstructor::class);
            $this->assertEquals('parent', $parent->foo);
        } catch (InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail("Auryn failed to locate the ");
        }
    }

    public function testChildWithoutConstructorMissingParam(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Auryn\ChildWithoutConstructor::__construct() declared in Auryn\ParentWithConstructor');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector = new Injector();
        $injector->define(ParentWithConstructor::class, array(':foo' => 'parent'));
        $injector->make(ChildWithoutConstructor::class);
    }

    public function testInstanceClosureDelegates(): void
    {
        $injector = new Injector();
        $injector->delegate(DelegatingInstanceA::class, function (DelegateA $d) {
            return new \Auryn\DelegatingInstanceA($d);
        });
        $injector->delegate(DelegatingInstanceB::class, function (DelegateB $d) {
            return new \Auryn\DelegatingInstanceB($d);
        });

        $a = $injector->make(DelegatingInstanceA::class);
        $b = $injector->make(DelegatingInstanceB::class);

        $this->assertInstanceOf(DelegateA::class, $a->a);
        $this->assertInstanceOf(DelegateB::class, $b->b);
    }

    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exception in constructor');

        $injector = new Injector();

        try {
            $injector->make(ThrowsExceptionInConstructor::class);
        } catch (\Exception $e) {
        }

        $injector->make(ThrowsExceptionInConstructor::class);
    }
}
