<?php

namespace App\Services;

use Exception;
use App\Facades\Trans;
use App\Base\BaseRepository;

class Web extends BaseRepository
{

    /**
     * BREAD CRUMB
     * -------------------------------------------------------------------------
     */

    private $breadcrumbs = [];
    private $breadcrumbTitle = '';

    /**
     * Add Breadcrumb item
     *
     * @param string $text
     * @param string|array $route
     */
    public function addBreadcrumb($text, $route = '#')
    {
        if (is_array($text))
            $text =
                $this->breadcrumbs[] = [
                    'text' => $text,
                    'route' => is_array($route) ? route($route[0], $route[1] ?? []) : $route
                ];
    }

    /**
     * Get Breacrumbs Array
     *
     * @return array
     */
    public function getBreadcrumb()
    {
        return $this->breadcrumbs;
    }

    /**
     * Reset Breadcrumbs
     *
     * @return void
     */
    public function resetBreadcrumb()
    {
        $this->breadcrumbs = [];
        if ($setHome)
            $this->addBreadcrumb(__('lang.home'), [$homeRoute]);
    }

    public function setBreadcrumbTitle($title)
    {
        return $this->breadcrumbTitle = Trans::chose($title);
    }

    public function appendBreadcrumbTitle($title)
    {
        return $this->breadcrumbTitle =  $this->breadcrumbTitle . ' \ ' . Trans::chose($title);
    }

    public function getBreadcrumbTitle()
    {
        return $this->breadcrumbTitle;
    }
}
