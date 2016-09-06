<?php namespace im\Tenantable;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Domain
 *
 * @package im\Tenantable
 */
class Domain extends Model
{

    /**
     * @var string
     */
    protected $table = 'tenant_domains';

    /**
     * @var array
     */
    protected $fillable = ['domain', 'meta'];

    /**
     * @var array
     */
    protected $casts = ['domain' => 'string', 'meta' => 'collection'];

    /**
     * Domain constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setConnection(config('tenantable.database.default'));

        parent::__construct($attributes);
    }

    /**
     * @return mixed
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

}
