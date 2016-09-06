<?php namespace im\Tenantable\Events;

use Illuminate\Queue\SerializesModels;
use im\Tenantable\Tenant;

abstract class TenantableEvent
{
    use SerializesModels;

    public $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

}
