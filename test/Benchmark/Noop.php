<?php

namespace Auryn\Benchmark;

class Noop
{
    public function noop()
    {
        // call-target, intentionally left empty
    }

    public function namedNoop($name)
    {
        // call-target, intentionally left empty
    }

    public function typehintedNoop(noop $noop)
    {
        // call-target, intentionally left empty
    }
}
