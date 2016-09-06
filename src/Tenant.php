<?php namespace im\MultiTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Class Tenant
 *
 * @package im\MultiTenant
 */
class Tenant extends Model
{

    /**
     * @var string
     */
    protected $table = 'tenants';

    /**
     * @var array
     */
    protected $fillable = [
        'uuid',
        'domain',
        'driver',
        'host',
        'database',
        'user',
        'password',
        'prefix',
        'meta'
    ];

    /**
     * @var array
     */
    protected $casts = [
        'uuid' => 'string',
        'domain' => 'string',
        'driver' => 'string',
        'host' => 'string',
        'database' => 'string',
        'user' => 'string',
        'password' => 'string',
        'prefix' => 'string',
        'meta' => 'collection'
    ];

    /**
     * @var bool
     */
    protected $shouldBeEncrypted = false;

    /**
     * Tenant constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setConnection(config('tenantable.database.default'));

        parent::__construct($attributes);
    }

    /**
     * Boot tenant model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {

            $uuids = app('db')->connection(config('tenantable.database.default'))->table('tenants')->lists('uuid');

            $uuid = $tenant->generateUuid();

            while (in_array($uuid, $uuids)) {
                $uuid = $tenant->generateUuid();
            }

            $tenant->persistUuid($uuid);
        });

    }

    /**
     * @return string
     */
    public function generateUuid()
    {
        return strtolower(
            substr(str_shuffle(preg_replace("/[^A-Za-z0-9]/", '',
                bcrypt(time() . $this->toJson() . microtime()))), 0, 8)
        );
    }

    /**
     * @param $value
     */
    public function persistUuid($value)
    {
        $this->attributes['uuid'] = $value;
    }

    /**
     * @return mixed
     */
    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * @param $value
     * @return string
     */
    public function getHostAttribute($value)
    {
        return $this->decryptAttribute($value);
    }

    /**
     * @param $value
     * @return string
     */
    private function decryptAttribute($value)
    {
        if ($this->shouldBeEncrypted && $value != '') {
            return Crypt::decrypt($value);
        }

        return '';
    }

    /**
     * @param $value
     * @return string
     */
    public function getDatabaseAttribute($value)
    {
        return $this->decryptAttribute($value);
    }

    /**
     * @param $value
     * @return string
     */
    public function getUsernameAttribute($value)
    {
        return $this->decryptAttribute($value);
    }

    /**
     * @param $value
     * @return string
     */
    public function getPasswordAttribute($value)
    {
        return $this->decryptAttribute($value);
    }

    /**
     * @param $value
     */
    public function setHostAttribute($value)
    {
        $this->encryptAttribute('host', $value);
    }

    /**
     * @param $attribute
     * @param $value
     * @return string
     */
    private function encryptAttribute($attribute, $value)
    {
        if (!$this->shouldBeEncrypted) return;

        if (empty($value)) {

            $this->attributes[$attribute] = '';

            return;
        }

        $this->attributes[$attribute] = Crypt::encrypt($value);
    }

    /**
     * @param $value
     */
    public function setDatabaseAttribute($value)
    {
        $this->encryptAttribute('database', $value);
    }

    /**
     * @param $value
     */
    public function setUsernameAttribute($value)
    {
        $this->encryptAttribute('username', $value);
    }

    /**
     * @param $value
     */
    public function setPasswordAttribute($value)
    {
        $this->encryptAttribute('password', $value);
    }

    /**
     * @param $value
     */
    public function setUuidAttribute($value)
    {

    }

}
