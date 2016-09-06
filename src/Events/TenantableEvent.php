<?php namespace im\MultiTenant\Events;

use Illuminate\Queue\SerializesModels;
use im\MultiTenant\Tenant;

abstract class TenantableEvent
{
    use SerializesModels;

    public $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

}
