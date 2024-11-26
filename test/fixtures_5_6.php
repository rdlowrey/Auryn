<?php

namespace Auryn;

class NoTypehintNoDefaultConstructorVariadicClass
{
    public $testParam = 1;
    public function __construct(TestDependency $val1, ...$arg)
    {
        $this->testParam = $arg;
    }
}

class TypehintNoDefaultConstructorVariadicClass
{
    public $testParam = 1;
    public function __construct(TestDependency ...$arg)
    {
        $this->testParam = $arg;
    }
}
