<?php

if (!function_exists('recuresive_array_merge')) {
    /**
     * Merge Array
     *
     * @param array $array1
     * @param array $array2
     * @return array merged array
     */
    function recuresive_array_merge(array $array1, array $array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = recuresive_array_merge($merged[$key], $value);
            } else if (is_numeric($key)) {
                if (!in_array($value, $merged)) {
                    $merged[] = $value;
                }
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}

if (!function_exists('route_api_opt')) {
    /**
     * Autogenerate option route untuk api menyesuakan url home dan api nya
     *
     * @param type $appsUrl
     * @param type $apiUrl
     * @return array format
     *      prefix
     *      domain
     */
    function route_api_opt($appsUrl, $apiUrl)
    {
        $appsPathInfo = parse_url($appsUrl);
        $apiPathInfo = parse_url($apiUrl);
        if (!isset($appsPathInfo['path'])) $appsPathInfo['path'] = '';
        if (!isset($apiPathInfo['path'])) $apiPathInfo['path'] = '';
        $prefix = str_replace($appsPathInfo['path'], '', $apiPathInfo['path']);
        $routeOpt = [
            'prefix' => '',
            'domain' => ''
        ];
        if ($prefix) {
            $routeOpt['prefix'] = $prefix;
        }
        if ($appsPathInfo['host'] != $apiPathInfo['host']) {
            $routeOpt['domain'] = $apiPathInfo['host'];
        }
        return $routeOpt;
    }
}

if (!function_exists('route_web_opt')) {
    /**
     * Autogenerate option route untuk api menyesuakan url home dan api nya
     *
     * @param type $appsUrl
     * @param type $apiUrl
     * @return array format
     *      domain
     */
    function route_web_opt($appsUrl, $apiUrl)
    {
        $appsPathInfo = parse_url($appsUrl);
        $apiPathInfo = parse_url($apiUrl);
        if (!isset($appsPathInfo['path'])) $appsPathInfo['path'] = '';
        if (!isset($apiPathInfo['path'])) $apiPathInfo['path'] = '';
        $routeOpt = ['domain' => ''];
        if ($appsPathInfo['host'] != $apiPathInfo['host']) {
            $routeOpt['domain'] = $appsPathInfo['host'];
        }
        return $routeOpt;
    }
}

if (!function_exists('is_route')) {
    /**
     * detect apakah route yang sedang diakses sekarang adalah route tertentu
     *
     * @param string $routeName nama route yang akan dicek
     * @param type $class
     *
     * @return string nama class
     */
    function is_route($routeName, $class = 'active')
    {
        $isTrue = Route::current()->getName() == $routeName;
        if (is_array($routeName)) {
            $isTrue = in_array(Route::current()->getName(), $routeName);
        }
        return $isTrue ? $class : '';
    }
}

if (!function_exists('is_route_prefix')) {

    function is_route_prefix($route, $class = 'active')
    {
        $curPrefix = trim(Route::current()->getPrefix(), '/');
        $isTrue = $curPrefix == $route;
        if (is_array($route)) {
            $isTrue = in_array($curPrefix, $route);
        }
        return $isTrue ? $class : '';
    }
}

if (!function_exists('pagination_format')) {
    /**
     * Create a formatted pagination array
     *
     * @param int $count
     * @param int $offset
     * @param int $limit
     * @return array format
     *      total
     *      per_page
     *      current_page
     *      from
     *      to
     */
    function pagination_format(int $count, int $offset = 1, int $limit = 10)
    {
        $curPage = 1;
        $toPage = 1;
        if ($limit > 0) {
            $curPage = (int) floor(($offset + 1) / $limit) + 1;
            $toPage = (int) floor(($count + 1) / $limit) + 1;
        }
        $paginationData = [
            'total' => $count,
            'per_page' => $limit,
            'current_page' => $curPage,
            'from' => 1,
            'to' => $toPage,
        ];

        return $paginationData;
    }
}

if (!function_exists('pagination_convert_link')) {
    /**
     * convert link pagination default laravel menjadi default system asalnya page ke offset & limit
     *
     * @param string $url
     * @param int $limit
     * @return string
     */
    function pagination_convert_link($url, $limit)
    {
        $path = parse_url($url);

        if (!isset($path['query'])) return $url;

        parse_str($path['query'], $queryParams);

        $queryParams['offset'] = ($queryParams['page'] - 1) * $limit;
        $queryParams['limit'] = $limit;
        unset($queryParams['page']);

        $domain = $path['scheme'] . '://' . $path['host'];
        if (isset($path['port'])) $domain .= ':' . $path['port'];

        $newUrl = $domain . $path['path'] . '?' . http_build_query($queryParams);

        return $newUrl;
    }
}

if (!function_exists('pagination_generate')) {
    /**
     * Generate Laravel Paginator from paginator array
     *
     * @param array $paginationParam
     *      data array data yg ditampilkannya (digunakan untuk generate array dari pagination)
     *      count int
     *      offset int
     *      limit int limit perpage
     * @param string $path url path utama yg digunakan di pagination
     * @param string $view view pagination
     * @return \Illuminate\Pagination\Paginator pagination object
     */
    function pagination_generate($paginationParam, $path, $view = 'component.pagination')
    {
        $dataPagination = pagination_format(
            $paginationParam['count'],
            $paginationParam['offset'],
            $paginationParam['limit']
        );
        //ubah current page berdasarkan perhitungan
        request()->merge(['page' => $dataPagination['current_page']]);

        \Illuminate\Pagination\Paginator::defaultView($view);

        // set current page
        // $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        // set limit
        $perPage = $paginationParam['limit'] ? $paginationParam['limit'] : ($paginationParam['count'] ? $paginationParam['count'] : 10);

        $results = new \Illuminate\Pagination\LengthAwarePaginator(collect($paginationParam['data']), $paginationParam['count'], $perPage);

        return $results->withPath($path);
    }
}

if (!function_exists('is_decimal')) {
    /**
     *  mengecek apakah sebuah nilai angka mengandung decimal atau tidak
     *
     * @param type $value yang akan dicek (float/double)
     * @return boolean
     */
    function is_decimal($value)
    {
        // return ((float) $value !== floor($value));
        return is_numeric($value) && floor($value) != $value;
    }
}

if (!function_exists('clean_number_format')) {
    /**
     * number_format sekaligus menghilangkan decimal yg tidak perlu misal
     *  1.500,00  --> 1.500
     *  1.500,02  --> 1.500,02
     * @param mixed $value yang akan diformat
     * @param int $precision jumlah decimal yang diinginkan muncul
     * @param string $decimalSeparator separator desimal, default ','
     * @param string $thousandSeparator separator ribuan, default '.'
     * @return string
     */
    function clean_number_format(
        $value,
        $precision = 0,
        $decimalSeparator = ',',
        $thousandSeparator = '.'
    ) {
        //    return $value;
        if ($value == "") {
            return "0";
        } else {
            if (!is_decimal(trim($value)))
                return number_format($value, 0, $decimalSeparator, $thousandSeparator);
            else
                return number_format($value, $precision, $decimalSeparator, $thousandSeparator);
        }
    }
}

if (!function_exists('template_assets')) {

    /**
     * generate file url ke assets template yang aktiv : mainurl.tld/assets/template/TEMPLATENAME/
     *
     * @param string $folder
     * @param string $file
     *
     * @return string Full URL
     */
    function template_assets($folder = '', $file = '')
    {
        return request()->getScheme() . '://' . request()->getHttpHost()
            . "/assets/template/default/"
            . trim(trim($folder), '/') . "/"
            . ltrim($file, '/');
    }
}
