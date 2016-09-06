<?php namespace im\Tenantable\Events;

use im\Tenantable\Resolver;

class TenantNotResolvedEvent
{
    public $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }
}
