<?php namespace im\MultiTenant\Events;

use im\MultiTenant\Resolver;

class TenantNotResolvedEvent
{
    public $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }
}
