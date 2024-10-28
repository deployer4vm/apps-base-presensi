<?php

namespace App\Base;

use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

use App\Facades\CacheConfig;

class BaseModel extends Model
{
    public function __construct(array $attributes = [])
    {
        // unset kembali model_tenant_id jika di model per tenant
        if(property_exists($this,'tenantId'))
            static::$tenantId = 0;
        //     config(['model_tenant_id',null]);
        
        parent::__construct($attributes);
    }
    
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * override fillable
     */
    public function getFillable()
    {
        if (empty($this->fillable)) $this->setAutoFillableWithCache();
        return $this->fillable;
    }

    /**
     * fungsinya untuk load list field tidak live query ke database, tapi ambil dari cache
     */
    public function setAutoFillableWithCache()
    {
        $tableName = $this->getTable();
        $key = 'autoFillable-' . $this->getConnectionName() . '-' . $tableName;
        $fillableKeyList = CacheConfig::getConfig('autoFillable-list', [], false);
        $fillable = CacheConfig::getConfig($key, false, false);
        if ($fillable && in_array($key, $fillableKeyList)) {
            $this->fillable = $fillable;
        } else {
            $this->setAutoFillable();
            CacheConfig::setConfig(
                'autoFillable-' . $this->getConnectionName() . '-' . $tableName,
                $this->fillable
            );

            if (!in_array($key, $fillableKeyList))
                $fillableKeyList[] = $key;

            CacheConfig::setConfig('autoFillable-list', $fillableKeyList);
        }
    }

    /**
     * Set $fillable from database
     *
     * @return void
     */
    public function setAutoFillable()
    {
        $fields = Schema::connection($this->getConnectionName())
            ->getColumnListing($this->getTable());
        $guarded = $this->getGuarded();
        $this->fillable = array_filter($fields, function ($v) use ($guarded) {
            return !in_array($v, $guarded);
        });
    }

    public function createdby()
    {
        return $this->belongsTo('hpsynapse\moduser\Models\User', 'created_by', 'id');
    }

    public function updatedby()
    {
        return $this->belongsTo('hpsynapse\moduser\Models\User', 'updated_by', 'id');
    }
}
