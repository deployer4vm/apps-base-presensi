<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Exception;

use App\Base\BaseRepository;

class Trans extends BaseRepository
{

    public function fallbackLocal()
    {
        return config('AppConfig.system.fallback_locale');
    }

    public function getLocale()
    {
        return App::getLocale();
    }

    public function chose($lang)
    {
        return $lang[$this->getLocale()] ?? $lang[$this->fallbackLocal()] ?? $lang;
    }
}
