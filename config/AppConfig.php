<?php
require app_path('Helpers/Helper.php');

/**
 * Filter acl
 */
if (!function_exists('processAcl')) {
    function processAcl($acl, $packageName, $aclPrefix, $children)
    {
        foreach ($children as $aclId => $value) {
            if (isset($value['enable']) && $value['enable'] && $value['acl_config']['show']) {
                $aclPrefixTmp = $aclPrefix . '.' . $aclId;

                $aclData = $value['acl_config'];
                $aclData['parent'] = $aclPrefix;
                $aclData['tenant_group_id'] = $value['tenant_group_id'] ?? 0;
                $aclData['acl_caption'] = $value['acl_caption'] ?? $value['caption'];
                $aclData['acl_description'] = $value['acl_description'] ?? $value['description'];

                $acl[$packageName]['children'][$aclPrefixTmp] = $aclData;
                //jika masih ada child nya proses terus
                if (isset($value['children'])) {
                    unset($acl[$aclPrefixTmp]['children']);
                    $acl = processAcl($acl, $packageName, $aclPrefixTmp, $value['children']);
                }
            }
        }
        return $acl;
    }
}

/**
 * Generate sidenav
 */
if (!function_exists('processSidenav')) {
    function processSidenav($children)
    {
        $res = [];
        foreach ($children as $aclId => $value) {
            if (isset($value['enable']) && $value['enable'] && $value['is_navbar']) {
                $res[$aclId] = $value;
                //jika masih ada child nya proses terus
                if (isset($value['children'])) {
                    $res[$aclId]['children'] = processSidenav($res[$aclId]['children']);
                    if (count($res[$aclId]['children']) == 0) {
                        unset($res[$aclId]['children']);
                    }
                }
            }
        }
        return $res;
    }
}

if (!function_exists('clean_endpoint')) {
    function clean_endpoint($endpoint)
    {
        $cleaned = [];
        foreach ($endpoint as $key => $value) {
            $cleaned[$key] = is_array($value) ? clean_endpoint($value) : str_replace('//', '/', $value);
        }

        return $cleaned;
    }
}

if (!function_exists('get_module_folder')) {
    function get_module_folder($packageConfigPath)
    {
        $path = str_replace('/packageconfig.json', '', $packageConfigPath);
        $paths = explode('/', $path);

        return array_pop($paths);
    }
}

/**
 * Config utama yang menyimpan semua config aplikasi. Datanya disimpan di app/MainApp/config
 */
$mainAppPath = __DIR__ . '/../app/MainApp';
$client = json_decode(file_get_contents($mainAppPath . '/config/client.json'), true);

if (file_exists($mainAppPath . '/config/clientEnv.json')) {
    $tmpEnvClient = json_decode(file_get_contents($mainAppPath . '/config/clientEnv.json'), true);
    //save ulang config pastikan tidak mengandung key yang tidak boleh diedit
    $client = recuresive_array_merge($client, $tmpEnvClient);
} else {
    file_put_contents($mainAppPath . '/config/clientEnv.json', json_encode($client, JSON_PRETTY_PRINT));
    //$tmpEnvClient = $client;
}

//load config listener.json jika ada
// $listener = [];
// if(file_exists($mainAppPath . '/config/listener.json')){
//     $listener = json_decode(file_get_contents($mainAppPath . '/config/listener.json'), true);
// }

$keyConfig = json_decode(file_get_contents(__DIR__ . '/../resources/assets/src/config.json'), true);

/**
 * Load config system.json
 * -----------------------------------------------------------------------------
 */
$system = json_decode(file_get_contents($mainAppPath . '/config/system.json'), true);

if (file_exists($mainAppPath . '/config/systemEnv.json')) {
    $tmpEnvSystem = json_decode(
        file_get_contents($mainAppPath . '/config/systemEnv.json'),
        true
    );
} else {
    file_put_contents(
        $mainAppPath . '/config/systemEnv.json',
        json_encode($system, JSON_PRETTY_PRINT)
    );
    $tmpEnvSystem = $system;
}

$newEnv = [];
//hanya load systemEnv yang boleh dieditnya saja
foreach ($keyConfig['allowed_systemEnv_key'] as $value) {
    if (isset($tmpEnvSystem[$value])) {
        $newEnv[$value] = $tmpEnvSystem[$value];
    }
}
if (count($newEnv) >= 1) {
    //save ulang config pastikan tidak mengandung key yang tidak boleh diedit
    file_put_contents(
        $mainAppPath . '/config/systemEnv.json',
        json_encode($newEnv, JSON_PRETTY_PRINT)
    );
    $system = recuresive_array_merge($system, $newEnv);
}

/**
 * Load config system.json project yg aktif (multiproject), jika active dan ada
 * lalu mergekan dengan system.json utama
 * ---------------------------------------------------------------------------------
 */
$projectPath = $mainAppPath . '/Projects/' . $client['project_code'];
if (
    isset($system['multiproject']['active'])
    && $system['multiproject']['active'] == 1
    && file_exists($projectPath . '/config/system.json')
) {
    $perProjectSystem = json_decode(
        file_get_contents($projectPath . '/config/system.json'),
        true
    );

    $newEnv = [];
    //hanya load systemEnv yang boleh dieditnya saja
    foreach ($keyConfig['allowed_systemEnv_key'] as $value) {
        if (isset($perProjectSystem[$value])) {
            $newEnv[$value] = $perProjectSystem[$value];
        }
    }
    if (count($newEnv) >= 1) {
        //save ulang config pastikan tidak mengandung key yang tidak boleh diedit
        file_put_contents(
            $projectPath . '/config/system.json',
            json_encode($newEnv, JSON_PRETTY_PRINT)
        );
        $system = recuresive_array_merge($system, $newEnv);
    }
}

/**
 * Proses package & packageLocal config.
 * merge config package & packageLocal menjadi packageLocal, karena package akan digunakan untuk default config package (module ataupun lib)
 * -------------------------------------------------------------------------------------------------------------------------------------------
 */

/*
load config module & lib
 */
$moduleList = array_merge(
    glob(base_path('app/MainApp/Modules/*/packageconfig.json')),
    glob(base_path('vendor/hp-synapse/*/packageconfig.json'))
);
$package = [];
$packageFolder = [];
foreach ($moduleList as $path) {
    $tmpPackage = json_decode(file_get_contents($path), true);
    if (!isset($tmpPackage['load_priority'])) {
        $tmpPackage['load_priority'] = 99;
    }
    $package[$tmpPackage['package_namespace']] = $tmpPackage;
    $packageFolder[get_module_folder($path)] = $tmpPackage['package_namespace'];
}

$packageCollection = collect($package);
$sortedPackage = $packageCollection->sortBy('load_priority');
$package = $sortedPackage->toArray();

$packageOld = '';
if (file_exists($mainAppPath . '/config/package.json')) {
    $packageOld = file_get_contents($mainAppPath . '/config/package.json');
}

$packageText = json_encode($package, JSON_PRETTY_PRINT);

//save hanya jika ada perubahan
if ($packageOld != $packageText) {
    file_put_contents($mainAppPath . '/config/package.json', $packageText);
}

//merge config package dengan package local
$packageLocalString = '';
if (file_exists($mainAppPath . '/config/packageLocal.json')) {
    $packageLocalString = file_get_contents($mainAppPath . '/config/packageLocal.json');
    $tmpPackageLocal = json_decode($packageLocalString, true);
} else {
    $tmpPackageLocal = [];
}

/**
 * Load packageLocalEnv.json
 */
if (file_exists($mainAppPath . '/config/packageLocalEnv.json')) {
    $tmpPackageLocalEnv = json_decode(
        file_get_contents($mainAppPath . '/config/packageLocalEnv.json'),
        true
    );
} else {
    file_put_contents(
        $mainAppPath . '/config/packageLocalEnv.json',
        json_encode($tmpPackageLocal, JSON_PRETTY_PRINT)
    );
    $tmpPackageLocalEnv = $tmpPackageLocal;
}

/**
 * Load config packageLocal.json project yg aktif (multiproject), jika active dan ada
 * untuk di mergekan dengan packageLocal.json utama
 * ---------------------------------------------------------------------------------
 */
$tmpPackageLocalPerProjectEnv = [];
if (
    isset($system['multiproject']['active'])
    && $system['multiproject']['active']
    && file_exists($projectPath . '/config/packageLocal.json')
) {
    $tmpPackageLocalPerProjectEnv = json_decode(
        file_get_contents($projectPath . '/config/packageLocal.json'),
        true
    );
}

/**
 * Load config packageLocal.json seluruh tenant
 * untuk di mergekan dengan _packageLocal.json utama
 * ---------------------------------------------------------------------------------
 */
$tmpPackageLocalPerTenant = [];

if (isset($system['multitenant']['active']) && $system['multitenant']['active']) {

    $tenantPath = base_path(
        (isset($system['multiproject']['active']) && $system['multiproject']['active']) ?
            'app/MainApp/Projects/' . $client['project_code'] . '/Tenants/*/config/packageLocal.json' :
            'app/MainApp/Tenants/*/config/packageLocal.json'
    );
    $tenantList = glob($tenantPath);

    if ($tenantList) {
        foreach ($tenantList as $path) {
            $tenantId = explode('Tenants/ID', str_replace('/config/packageLocal.json', '', $path));
            $tmpPackageLocalPerTenant[$tenantId[1]] = json_decode(file_get_contents($path), true);
        }
    }
}

$multiTenantVuePrefix = '';
$multiTenantLaravelPrefix = '';

if (
    isset($system['multitenant']['active'])
    && $system['multitenant']['active']
    && (!isset($system['multitenant']['detect_mode'])
        || $system['multitenant']['detect_mode'] == 1)
) {
    $multiTenantVuePrefix = '/:group_app';
    $multiTenantLaravelPrefix = '/{group_app}';
}

$envEndpoint = $client['endpoint'][$system['mode']];

$homeSlug = $envEndpoint['home_slug'] ?? '';
$homeSlug = $homeSlug ? '/' . trim($homeSlug, '/') : '';
// $homeSlug = str_replace('//', '/', $homeSlug);
// $homeSlug = trim($homeSlug, '/');
// $homeSlug = $homeSlug  ? '/' . $homeSlug : '';

//initiate config ednpoint.json
$endpoint = [
    'domain' => $envEndpoint['domain'],
    'admin' => [
        'app' => $homeSlug . $multiTenantVuePrefix . $envEndpoint['admin'],
        'auth' => $homeSlug . $multiTenantVuePrefix,
    ],
    'frontend' => [
        'app' => $homeSlug . $multiTenantVuePrefix . $envEndpoint['frontend'],
        'auth' => $homeSlug . $multiTenantVuePrefix,
    ],
    'api' => [
        'app' => $homeSlug . $envEndpoint['api'],
        'auth' => $homeSlug,
    ],
    'laravel' => [
        'admin' => [
            'app' => $multiTenantLaravelPrefix . $envEndpoint['admin'],
            'auth' => $multiTenantLaravelPrefix,
        ],
        'frontend' => [
            'app' => $multiTenantLaravelPrefix . $envEndpoint['frontend'],
            'auth' => $multiTenantLaravelPrefix,
        ],
        'api' => [
            'app' => $envEndpoint['api'],
            'auth' => '',
        ],
    ],
];
$newPackageLocal = []; //untuk filtered packageLocal.json yang akan disave ulang
$newPackageLocalEnv = []; //untuk filtered packageLocalEnv.json yang akan disave ulang
$packageLocal = []; //pakcageLocal akhir setelah proses filtering & merging, dan disave ke MainApp/config/_packageLocal.env dan diload sebagai config utama

$acl = [];
$tmpSidenav = [];
$tmpSidenavNoPos = []; //package yg tidak diset access.pos nya
$sidenav = [];

// looping semua packageconfig yg ada untuk proses filtering dan pemrosesan yang menghasilkan _packageLocal.json, _sidenav.json dan _acl.json di MainApp/config
foreach ($package as $item) {

    // get data packageconfig dari packageLocal.json di MainApp/config untuk diproses selanjutnya
    $newPackageLocal[$item['package_namespace']] = $tmpPackageLocal[$item['package_namespace']] ?? [];

    // get data packageconfig dari packageLocalEnv.json di MainApp/config untuk diproses selanjutnya
    $newPackageLocalEnv[$item['package_namespace']] = $tmpPackageLocalEnv[$item['package_namespace']] ?? [];

    //hapus key packageconfig.json yang tidak boleh diedit, baik yg dari packageLocal.json maupun packageLocalEnv.json
    foreach ($keyConfig['protected_packageLocal_key'] as $value) {
        if (isset($newPackageLocal[$item['package_namespace']][$value])) {
            unset($newPackageLocal[$item['package_namespace']][$value]);
        }

        if (isset($newPackageLocalEnv[$item['package_namespace']][$value])) {
            unset($newPackageLocalEnv[$item['package_namespace']][$value]);
        }
    }

    // merge packageconfig asli dari masing-masing module dengan packageconfig dari packageLocal.json di MainApp/config
    $packageLocal[$item['package_namespace']] = recuresive_array_merge(
        $item,
        $newPackageLocal[$item['package_namespace']]
    );

    // merge packageconfig sebelumnya (hasil merge) dengan packageconfig dari packageLocalEnv.json di MainApp/config
    // if ($system['mode'] == 'dev') {
    $packageLocal[$item['package_namespace']] = recuresive_array_merge(
        $packageLocal[$item['package_namespace']],
        $newPackageLocalEnv[$item['package_namespace']]
    );
    // }

    /**
     * proses generate _acl.json dan _sidenav.json
     */
    if ($packageLocal[$item['package_namespace']]['enable']) {
        //proses _acl.json
        if (
            isset($packageLocal[$item['package_namespace']]['access']['has_acl'])
            && $packageLocal[$item['package_namespace']]['access']['has_acl']
        ) {
            $acl[$item['package_namespace']] = [
                'acl_caption' =>
                $packageLocal[$item['package_namespace']]['access']['acl_caption'] ??
                    $packageLocal[$item['package_namespace']]['access']['caption'],
                'acl_description' =>
                $packageLocal[$item['package_namespace']]['access']['acl_description'] ??
                    $packageLocal[$item['package_namespace']]['access']['description'],
                'tenant_group_id' =>
                $packageLocal[$item['package_namespace']]['access']['tenant_group_id'] ?? 0,
            ];
            if (isset($packageLocal[$item['package_namespace']]['access']['children'])) {
                $acl = processAcl(
                    $acl,
                    $item['package_namespace'],
                    $item['package_namespace'],
                    $packageLocal[$item['package_namespace']]['access']['children']
                );
            }
        }

        //proses _sidenav
        if (
            isset($packageLocal[$item['package_namespace']]['access']['is_navbar'])
            && $packageLocal[$item['package_namespace']]['access']['is_navbar']
        ) {
            $tmpSidenavTmp = [
                'package_namespace' => $item['package_namespace'],
                $item['package_namespace'] => $packageLocal[$item['package_namespace']]['access'],
            ];

            if (isset($tmpSidenavTmp[$item['package_namespace']]['children'])) {
                $tmpSidenavTmp[$item['package_namespace']]['children'] =
                    processSidenav($tmpSidenavTmp[$item['package_namespace']]['children']);
                if (count($tmpSidenavTmp[$item['package_namespace']]['children']) == 0) {
                    unset($tmpSidenavTmp[$item['package_namespace']]['children']);
                }
            }

            if (isset($packageLocal[$item['package_namespace']]['access']['position'])) {
                $tmpSidenav[$packageLocal[$item['package_namespace']]['access']['position']] = $tmpSidenavTmp;
            } else {
                $tmpSidenavNoPos[] = $tmpSidenavTmp;
            }
        }
    }
}

// merge packageconfig sebelumnya (hasil merge) dengan packageconfig dari packageLocal.json di Mmasing-masing config project (jika project multi project)
$packageLocal = recuresive_array_merge(
    $packageLocal,
    $tmpPackageLocalPerProjectEnv
);

// merge config packageLocal ke masing2 packageLocal per tenant
$packageLocalPerTenant = [];
foreach ($tmpPackageLocalPerTenant as $tenantId => $pertenant) {
    if ($pertenant) {
        $packageLocalPerTenant[$tenantId] = recuresive_array_merge(
            $packageLocal,
            $pertenant
        );
    }
}

foreach ($tmpSidenavNoPos as $value) {
    $tmpSidenav[] = $value;
}
ksort($tmpSidenav);
foreach ($tmpSidenav as $key => $value) {
    $sidenav[$value['package_namespace']] = $value[$value['package_namespace']];
}

$filePath = ""; //path real saat build
$packagePath = ""; //path untuk load selain main.js
$packageMainPath = ""; //path untuk load main.js

$pathToBase = str_replace('\\', '/', base_path(''));

if ($system['web_admin']['full_vue']) {
    $moduleBuildJs = [
        "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
        "// This files is autogenerated on build and by app-generator \n",
        "// containt list all build.js for every module registered to this project \n\n",
        "module.exports = function(fs, mix) {\n",
    ];
}

if ($system['web_admin']['web']) {
    $moduleWebBuildJs = [
        "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
        "// This files is autogenerated on build and by app-generator \n",
        "// containt list all build.js for every module registered to this project \n\n",
        "module.exports = function(fs, mix) {",
    ];
}

//---
$moduleMainJs = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// containt list all main.js for every module registered to this project \n\n",
];
//---
$moduleStore = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all vuex state for every module registered to this project \n\n",
];
$moduleStoreNamespace = [];
//---
$moduleStoreConfig = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all vuex auto resource config for every module registered to this project \n\n",
];
$moduleStoreConfigNamespace = [];
//---
$moduleRouter = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all router for every module registered to this project \n\n",
];
$moduleRouterNamespace = [];
//---
$moduleRouterAdmin = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all router admin endpoint for every module registered to this project \n\n",
];
$moduleRouterAdminNamespace = [];

//---
//--- PER TENANT
//---
$moduleStorePerTenant = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all vuex state custom per tenant for every module registered to this project \n\n",
];
$moduleStoreNamespacePerTenant = [];
//---
$moduleStoreConfigPerTenant = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all vuex auto resource custom per tenant for every module registered to this project \n\n",
];
$moduleStoreConfigNamespacePerTenant = [];
//---
$moduleRouterPerTenant = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all router for every module registered to this project \n\n",
];
$moduleRouterNamespacePerTenant = [];
//---
$moduleRouterAdminPerTenant = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all router admin endpoint for every module registered to this project \n\n",
];
$moduleRouterAdminNamespacePerTenant = [];

$hpsynapse = include __DIR__ . DIRECTORY_SEPARATOR . 'hpsynapse.php';

$binding = $hpsynapse['bindings'];
/*$binding = empty($hpsynapse['bindings']) ? [
'class' => [],
'interface' => [],
'route' => [],
'alias' => []
] : $hpsynapse['bindings'];*/
$providers = [];

foreach ($packageLocal as $item) {
    /*
 generate binding masing-masing module
 ----------------------------
  */
    //binding interface
    if (isset($item['binding']) && isset($item['binding']['interface'])) {
        foreach ($item['binding']['interface'] as $contract => $service) {
            $binding['interface'][$contract] = $service;
        }
    }

    //provider per module
    if (isset($item['providers']) && $item['is_package'] == 0) {
        foreach ($item['providers'] as $provider) {
            $providers[] = $provider;
        }
    }

    /*
 generate endpoint masing-masing module
 ----------------------------
  */
    //$moduleEndpoints = $packageLocal[$item['package_namespace']]['endpoint'][$system['mode']];
    $moduleEndpoints = $item['endpoint'][$system['mode']];
    foreach ($moduleEndpoints as $app => $moduleEndpoint) {
        //jika module endpoint diawal "/" berarti tidak menggunakan apps endpoint
        if ($moduleEndpoint == '' || $moduleEndpoint[0] != '/') {
            $endpoint[$app][$item['package_namespace']] = $endpoint[$app]['app'] . '/' . $moduleEndpoint;
            $endpoint['laravel'][$app][$item['package_namespace']] = $endpoint['laravel'][$app]['app']
                . '/' . $moduleEndpoint;
        } else {
            $endpoint[$app][$item['package_namespace']] = $homeSlug . $multiTenantVuePrefix . $moduleEndpoint;
            $endpoint['laravel'][$app][$item['package_namespace']] = $multiTenantLaravelPrefix . $moduleEndpoint;
        }
        // $endpoint[$app][$item['package_namespace']] = str_replace('//', '/', $endpoint[$app][$item['package_namespace']]);
        // $endpoint['laravel'][$app][$item['package_namespace']] = str_replace('//', '/', $endpoint['laravel'][$app][$item['package_namespace']]);
        //jika memiliki fitur auth dan module user maka assign auth endpointnya
        if ($system['has_auth'] && isset($packageLocal['moduser']) && $packageLocal['moduser']['enable']) {
            $authEndpoint = $packageLocal['moduser']['auth_endpoint'][$system['mode']];
            if ($authEndpoint[0] != '/') {
                $endpoint[$app]['auth'] = $endpoint[$app]['app'] . '/' . $authEndpoint;
                $endpoint['laravel'][$app]['auth'] = $endpoint['laravel'][$app]['app'] . '/' . $authEndpoint;
            } else {
                $endpoint[$app]['auth'] = $homeSlug . $multiTenantVuePrefix . $authEndpoint;
                $endpoint['laravel'][$app]['auth'] = $multiTenantLaravelPrefix . $authEndpoint;
            }
            // $endpoint[$app]['auth'] = str_replace('//', '/', $endpoint[$app]['auth']);
            // $endpoint['laravel'][$app]['auth'] = str_replace('//', '/', $endpoint['laravel'][$app]['auth']);
        }
    }

    /*
 generate loader store, router, routerAdmin dan init.js untuk package
 -------------------------
  */
    if ($item['is_package']) {
        $filePath = "vendor/hp-synapse/" . $item['package_dir'] . "/src/";
        $packagePath = $pathToBase . "/vendor/hp-synapse/" . $item['package_dir'] . "/src/";
        $packageMainPath = $pathToBase . "/vendor/hp-synapse/" . $item['package_dir'] . "/src/";
    } else {
        $filePath = "app/MainApp/Modules/" . $item['package_dir'] . "/";
        $packagePath = "../../../Modules/" . $item['package_dir'] . "/";
        $packageMainPath = "../../Modules/" . $item['package_dir'] . "/";
    }

    //load package hanya jika aktif saja
    if ($packageLocal[$item['package_namespace']]['enable']) {

        //untuk loader build.js
        if ($system['web_admin']['full_vue'] && file_exists($filePath . "resources/js/build.js")) {
            $moduleBuildJs[] = '    require("' . $packageMainPath . 'resources/js/build")(fs,mix);' . "\n";
            // $moduleBuildJs[] = var build1 =
        }

        //untuk loader web-build.js
        if ($system['web_admin']['web'] && file_exists($filePath . "resources/js/webBuild.js")) {
            $moduleWebBuildJs[] = '    require("' . $packageMainPath . 'resources/js/webBuild")(fs,mix);' . "\n";
        }

        //untuk loader main.js
        if (file_exists($filePath . "resources/js/main.js")) {
            $moduleMainJs[] = 'require("' . $packageMainPath . 'resources/js/main");' . "\n";
        }

        //untuk loader vuex store
        if (file_exists($filePath . "resources/js/store/store.js")) {
            $moduleStore[] = "import " . $item['package_namespace'] . ' from "'
                . $packagePath . 'resources/js/store/store";' . "\n";
            $moduleStoreNamespace[] = "    ..." . $item['package_namespace'];
        }

        //untuk loader vuex auto resource config
        if (file_exists($filePath . "resources/js/store/storeConfig.js")) {
            $moduleStoreConfig[] = "import " . $item['package_namespace'] . ' from "'
                . $packagePath . 'resources/js/store/storeConfig";' . "\n";
            $moduleStoreConfigNamespace[] = "    ..." . $item['package_namespace'];
        }

        //untuk loader vue router
        if (file_exists($filePath . "resources/js/router/index.js")) {
            $moduleRouter[] = "import " . $item['package_namespace'] . ' from "'
                . $packagePath . 'resources/js/router/index";' . "\n";
            $moduleRouterNamespace[] = "    .concat(" . $item['package_namespace'] . ")";
        }

        //untuk loader vue router admin endpoint
        if (file_exists($filePath . "resources/js/router/indexAdmin.js")) {
            $moduleRouterAdmin[] = "import " . $item['package_namespace'] . ' from "'
                . $packagePath . 'resources/js/router/indexAdmin";' . "\n";
            $moduleRouterAdminNamespace[] = "    .concat(" . $item['package_namespace'] . ")";
        }
    }
}

// jika multi tenan maka loop vue router dan vuex module2 di masing2 tenant
$packagePathPerTenant = "../../../Modules/" . $item['package_dir'] . "/";

if (isset($system['multitenant']['active']) && $system['multitenant']['active']) {

    /**
     * loop router per tenant
     * -------------------------------------------------------------------------
     */

    /**
     * loop router indexAdmin.js
     */
    $tenantPath = base_path(
        (isset($system['multiproject']['active']) && $system['multiproject']['active'])
            ? ('app/MainApp/Projects/' . $client['project_code']
                . '/Tenants/*/Modules/*/resources/js/router/indexAdmin.js')
            : ('app/MainApp/Tenants/*/Modules/*/resources/js/router/indexAdmin.js')
    );
    $tenantList = glob($tenantPath);

    if ($tenantList) {
        foreach ($tenantList as $path) {
            $path = str_replace('\\', '/', $path);
            $tmp = str_replace('/resources/js/router/indexAdmin.js', '', $path);
            $tmp = explode('Tenants/ID', $tmp);
            $tenantData = explode('/Modules/', $tmp[1]);
            $tenantData[1] = preg_replace("/[\W_]+/", "", $tenantData[1]); // jangan ada spasi, jangan ada karakter lain selain huruf dan angka

            if (!isset($moduleRouterAdminNamespacePerTenant[$tenantData[0]])) {
                $moduleRouterAdminNamespacePerTenant[$tenantData[0]] = [];
            }

            $moduleRouterAdminPerTenant[] = "import " . $tenantData[1] . $tenantData[0]
                . ' from "' . $path . '";' . "\n";
            $moduleRouterAdminNamespacePerTenant[$tenantData[0]][] = ".concat(" . $tenantData[1] . $tenantData[0] . ")";
        }
        foreach ($moduleRouterAdminNamespacePerTenant as $tenantId => $tmpTenant) {
            $moduleRouterAdminNamespacePerTenant[$tenantId] = '"' . $tenantId . '": []' . implode('', $tmpTenant);
        }
    }

    /**
     * loop router index.js
     */
    $tenantPath = base_path(
        (isset($system['multiproject']['active']) && $system['multiproject']['active'])
            ? ('app/MainApp/Projects/' . $client['project_code']
                . '/Tenants/*/Modules/*/resources/js/router/index.js')
            : ('app/MainApp/Tenants/*/Modules/*/resources/js/router/index.js')
    );
    $tenantList = glob($tenantPath);

    if ($tenantList) {
        foreach ($tenantList as $path) {
            $path = str_replace('\\', '/', $path);
            $tmp = str_replace('/resources/js/router/index.js', '', $path);
            $tmp = explode('Tenants/ID', $tmp);
            $tenantData = explode('/Modules/', $tmp[1]);
            $tenantData[1] = preg_replace("/[\W_]+/", "", $tenantData[1]); // jangan ada spasi, jangan ada karakter lain selain huruf dan angka

            if (!isset($moduleRouterNamespacePerTenant[$tenantData[0]])) {
                $moduleRouterNamespacePerTenant[$tenantData[0]] = [];
            }

            $moduleRouterPerTenant[] = "import " . $tenantData[1] . $tenantData[0] . ' from "' . $path . '";' . "\n";
            $moduleRouterNamespacePerTenant[$tenantData[0]][] = "    .concat(" . $tenantData[1] . $tenantData[0] . ")";
        }
        foreach ($moduleRouterNamespacePerTenant as $tenantId => $tmpTenant) {
            $moduleRouterNamespacePerTenant[$tenantId] = '"' . $tenantId . '": []' . implode('', $tmpTenant);
        }
    }

    /**
     * loop vuex per tenant
     * -------------------------------------------------------------------------
     */

    $tenantPath = base_path(
        (isset($system['multiproject']['active']) && $system['multiproject']['active'])
            ? ('app/MainApp/Projects/' . $client['project_code'] . '/Tenants/*/Modules/*/resources/js/store/store.js')
            : ('app/MainApp/Tenants/*/Modules/*/resources/js/store/store.js')
    );
    $tenantList = glob($tenantPath);

    if ($tenantList) {
        foreach ($tenantList as $path) {
            $path = str_replace('\\', '/', $path);
            $tmp = str_replace('/resources/js/store/store.js', '', $path);
            $tmp = explode('Tenants/ID', $tmp);
            $tenantData = explode('/Modules/', $tmp[1]);
            $tenantData[1] = preg_replace("/[\W_]+/", "", $tenantData[1]); // jangan ada spasi, jangan ada karakter lain selain huruf dan angka

            if (!isset($moduleStoreNamespacePerTenant[$tenantData[0]])) {
                $moduleStoreNamespacePerTenant[$tenantData[0]] = [];
            }

            $moduleStorePerTenant[] = "import " . $tenantData[1] . $tenantData[0] . ' from "' . $path . '";' . "\n";
            $moduleStoreNamespacePerTenant[$tenantData[0]][] = "..." . $tenantData[1] . $tenantData[0];
        }
        foreach ($moduleStoreNamespacePerTenant as $tenantId => $tmpTenant) {
            $moduleStoreNamespacePerTenant[$tenantId] = '"' . $tenantId . '": {' . implode(',', $tmpTenant) . '}';
        }
    }

    /**
     * loop vuex auto resource per tenant
     * -------------------------------------------------------------------------
     */

    $tenantPath = base_path(
        (isset($system['multiproject']['active']) && $system['multiproject']['active'])
            ? ('app/MainApp/Projects/' . $client['project_code']
                . '/Tenants/*/Modules/*/resources/js/store/storeConfig.js')
            : ('app/MainApp/Tenants/*/Modules/*/resources/js/store/storeConfig.js')
    );
    $tenantList = glob($tenantPath);

    if ($tenantList) {
        foreach ($tenantList as $path) {
            $path = str_replace('\\', '/', $path);
            $tmp = str_replace('/resources/js/store/store.js', '', $path);
            $tmp = explode('Tenants/ID', $tmp);
            $tenantData = explode('/Modules/', $tmp[1]);
            $tenantData[1] = preg_replace("/[\W_]+/", "", $tenantData[1]); // jangan ada spasi, jangan ada karakter lain selain huruf dan angka

            if (!isset($moduleStoreConfigNamespacePerTenant[$tenantData[0]])) {
                $moduleStoreConfigNamespacePerTenant[$tenantData[0]] = [];
            }

            $moduleStoreConfigPerTenant[] = "import " . $tenantData[1] . $tenantData[0] . ' from "'
                . $path . '";' . "\n";
            $moduleStoreConfigNamespacePerTenant[$tenantData[0]][] = "..." . $tenantData[1] . $tenantData[0];
        }
        foreach ($moduleStoreConfigNamespacePerTenant as $tenantId => $tmpTenant) {
            $moduleStoreConfigNamespacePerTenant[$tenantId] = '"' . $tenantId . '": {' . implode(',', $tmpTenant) . '}';
        }
    }
}

// Clean endpoint
$endpoint = clean_endpoint($endpoint);

$newPackageLocalString = json_encode($newPackageLocal, JSON_PRETTY_PRINT);
//save ulang pakcageLocal hanya jika ada perubahan
if ($packageLocalString != $newPackageLocalString) {
    file_put_contents($mainAppPath . '/config/packageLocal.json', $newPackageLocalString);
}
file_put_contents($mainAppPath . '/config/packageLocalEnv.json', json_encode($newPackageLocalEnv, JSON_PRETTY_PRINT));
file_put_contents($mainAppPath . '/config/_endpoint.json', json_encode($endpoint, JSON_PRETTY_PRINT));

$system['path'] = [
    'MainApp' => app_path('MainApp'),
    'basePath' => base_path(''),
];

//---merge binding per module dengan binding utama (system)
//binding interface
if (isset($system['binding']['interface'])) {
    foreach ($system['binding']['interface'] as $contract => $service) {
        if (!isset($binding['interface'][$contract])) {
            $binding['interface'][$contract] = $service;
        }
    }
}
//binding class
if (isset($system['binding']['class'])) {
    foreach ($system['binding']['class'] as $contract => $service) {
        if (!isset($binding['class'][$contract])) {
            $binding['class'][$contract] = $service;
        }
    }
}
//binding route
if (isset($system['binding']['route'])) {
    foreach ($system['binding']['route'] as $contract => $service) {
        if (!isset($binding['route'][$contract])) {
            $binding['route'][$contract] = $service;
        }
    }
}
//binding alias
if (isset($system['binding']['alias'])) {
    foreach ($system['binding']['alias'] as $contract => $service) {
        if (!isset($binding['alias'][$contract])) {
            $binding['alias'][$contract] = $service;
        }
    }
}

//merge providers
if (!empty($providers)) {
    if (!isset($system['providers'])) {
        $system['providers'] = [];
    }

    $system['providers'] = array_merge($system['providers'], $providers);
}

//generate modulesMultitenant.js
$tenantConfigPath = $mainAppPath . '/config/_tenant.json';
if (file_exists($tenantConfigPath)) {
    $tenantList = json_decode(file_get_contents($tenantConfigPath), true);
} else {
    file_put_contents($tenantConfigPath, json_encode([], JSON_PRETTY_PRINT));
    $tenantList = [];
}

//jika file sidenav custom belum ada maka create default kosong
$sidenavConfigPath = $mainAppPath . '/config/sidenav.json';
if (!file_exists($sidenavConfigPath)) {
    file_put_contents($sidenavConfigPath, '{}');
}

$moduleMultitenant = [
    "// DO NOT EDIT MANUALY UNLESS YOU KNOW WHAT YOU ARE DOING \n",
    "// This files is autogenerated on build and by app-generator \n",
    "// load all multitenant component & function from all tenant multitenant.js \n\n",
];
$moduleMultitenantItem = [];
if (isset($system['multitenant']['active']) && $system['multitenant']['active']) {
    foreach ($tenantList as $tenant) {
        $tenantId = $tenant['id'] ?? $tenant;
        //untuk loader multitenant component registration
        if (
            file_exists(
                'app/MainApp/Projects/' . $client['project_code']
                    . '/Tenants/ID' . $tenantId . '/resources/js/multitenant.js'
            )
        ) {
            $moduleMultitenant[] = 'import ID' . $tenantId
                . ' from "@/../../../app/MainApp/Projects/'
                . $client['project_code'] . '/Tenants/ID' . $tenantId
                . '/resources/js/multitenant";' . "\n";
            $moduleMultitenantItem[] = '    ' . $tenantId . ': ID' . $tenantId;
        } else if (file_exists('app/MainApp/Tenants/ID' . $tenantId
            . '/resources/js/multitenant.js')) {
            $moduleMultitenant[] = 'import ID' . $tenantId
                . ' from "@/../../../app/MainApp/Tenants/ID' . $tenantId
                . '/resources/js/multitenant";' . "\n";
            $moduleMultitenantItem[] = '    ' . $tenantId . ': ID' . $tenantId;
        }
    }
}

//---generated js config
if ($system['web_admin']['full_vue']) {
    $moduleBuildJs[] = "};";
    file_put_contents($mainAppPath . '/resources/js/build.js', implode('', $moduleBuildJs));
}

if ($system['web_admin']['web']) {
    $moduleWebBuildJs[] = "};";
    file_put_contents($mainAppPath . '/resources/js/webBuild.js', implode('', $moduleWebBuildJs));
}

file_put_contents(
    $mainAppPath . '/resources/js/modules.js',
    implode('', $moduleMainJs)
);
file_put_contents(
    $mainAppPath . '/resources/js/modulesMultitenant.js',
    implode('', $moduleMultitenant)
        . "\nexport default {\n"
        . implode(",\n", $moduleMultitenantItem)
        . "\n};"
);
file_put_contents(
    $mainAppPath . '/resources/js/store/modules.js',
    implode('', $moduleStore)
        . "\nconst store = {\n"
        . implode(",\n", $moduleStoreNamespace)
        . "\n};\n\nexport default store;"
);
file_put_contents(
    $mainAppPath . '/resources/js/store/storeConfig.js',
    implode('', $moduleStoreConfig)
        . "\nconst storeConfig = {\n"
        . implode(",\n", $moduleStoreConfigNamespace)
        . "\n};\n\nexport default storeConfig;"
);
file_put_contents(
    $mainAppPath . '/resources/js/router/modules.js',
    implode('', $moduleRouter)
        . "\nconst routes = []\n"
        . implode("\n", $moduleRouterNamespace)
        . ";\n\nexport default routes;"
);
file_put_contents(
    $mainAppPath . '/resources/js/router/modulesAdmin.js',
    implode('', $moduleRouterAdmin)
        . "\nconst routes = []\n"
        . implode("\n", $moduleRouterAdminNamespace)
        . ";\n\nexport default routes;"
);
// per tenant
file_put_contents(
    $mainAppPath . '/resources/js/store/modulesPerTenant.js',
    implode('', $moduleStorePerTenant)
        . "\nconst store = {\n"
        . implode(",\n", $moduleStoreNamespacePerTenant)
        . "\n};\n\nexport default store;"
);
file_put_contents(
    $mainAppPath . '/resources/js/store/storeConfigPerTenant.js',
    implode('', $moduleStoreConfigPerTenant)
        . "\nconst store = {\n"
        . implode(",\n", $moduleStoreConfigNamespacePerTenant)
        . "\n};\n\nexport default storeConfig;"
);
file_put_contents(
    $mainAppPath . '/resources/js/router/modulesPerTenant.js',
    implode('', $moduleRouterPerTenant)
        . "\nvar routes = {\n"
        . implode(",\n", $moduleRouterNamespacePerTenant)
        . "};\n\nexport default routes;"
);
file_put_contents(
    $mainAppPath . '/resources/js/router/modulesAdminPerTenant.js',
    implode('', $moduleRouterAdminPerTenant)
        . "\nvar routes = {\n"
        . implode(",\n", $moduleRouterAdminNamespacePerTenant)
        . "};\n\nexport default routes;"
);

//---generated config json
file_put_contents(
    $mainAppPath . '/config/_binding.json',
    json_encode($binding, JSON_PRETTY_PRINT)
);
file_put_contents(
    $mainAppPath . '/config/_packageLocal.json',
    json_encode($packageLocal, JSON_PRETTY_PRINT)
);
file_put_contents(
    $mainAppPath . '/config/_packageLocalPertenant.json',
    json_encode($packageLocalPerTenant, JSON_PRETTY_PRINT)
);
file_put_contents(
    $mainAppPath . '/config/_system.json',
    json_encode($system, JSON_PRETTY_PRINT)
);
file_put_contents(
    $mainAppPath . '/config/_client.json',
    json_encode($client, JSON_PRETTY_PRINT)
);
file_put_contents(
    $mainAppPath . '/config/_acl.json',
    json_encode($acl, JSON_PRETTY_PRINT)
);
file_put_contents(
    $mainAppPath . '/config/_sidenav.json',
    json_encode($sidenav, JSON_PRETTY_PRINT)
);

/*
package dan module berisi config yang sama persis
 */
return [
    'client' => $client,
    'system' => $system,
    'binding' => $binding,
    'endpoint' => $endpoint,
    'packageLocal' => $packageLocal, //config2 dari module dan lib yang sudah diedit per project
    'packageLocalPerTenant' => $packageLocalPerTenant, //list package local per tenant
    'package' => $package, //config2 default dari module dan lib
    'packageFolder' => $packageFolder,
    // 'listener' => $listener,
    'sidenav' => $sidenav,
    'tenant' => $tenantList, //_tenant.json , list tenant
    'acl' => $acl, //_acl.json
];
