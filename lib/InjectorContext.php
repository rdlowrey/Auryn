<?php

namespace Auryn;

class InjectorContext extends Injector
{
    /** @var Injector|null  */
    private $parentContext = null;

    /**
     * @param Injector|null $parentContext
     */
    public function __construct(?Injector $parentContext)
    {
        $this->parentContext = $parentContext;
        parent::__construct($parentContext->reflector);
    }

    /**
     * For all types that are not shared, just pass the call through to
     * the normal injector class.
     *
     * For types that are shared, wrap any exception thrown with a specific
     * error message, that tells the user what they are probably doing wrong.
     *
     * @param $name
     * @param array $args
     * @return mixed
     * @throws InjectionException
     */
    public function make($name, array $args = array())
    {
        if ($this->parentContext === null || $this->parentContext->isKnownSharedType($name) === false) {
            return parent::make($name, $args);
        }

        try {
            return $this->parentContext->make($name);
        }
        catch (InjectionException $ie) {
            $message = sprintf(self::M_SHARED_CONTEXT_FAILED, $name, $ie->getMessage());
            throw new InjectionException(
                $ie->getDependencyChain(),
                $message,
                self::E_SHARED_CONTEXT_FAILED,
                $ie
            );
        }
    }
}