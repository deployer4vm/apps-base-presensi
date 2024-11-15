<?php

namespace App\Listeners\migrations;

use App\Facades\CacheConfig;

/**
 *
 */
trait ResetModelCacheTraits
{
    public function resetCache($eventName, $event)
    {
        $fillableKeyList = CacheConfig::getConfig('autoFillable-list', [], false);
        foreach ($fillableKeyList as $value) {
            // echo 'delete model fillable cache : ' . $value . "\n";
            CacheConfig::deleteConfig($value);
        }
    }
}
