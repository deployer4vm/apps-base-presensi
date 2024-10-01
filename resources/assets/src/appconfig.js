/*
load all application config
*/
let AppConfig = {
    system: require("../../../app/MainApp/config/_system.json"),
    binding: require("../../../app/MainApp/config/_binding.json"),
    client: require("../../../app/MainApp/config/_client.json"),
    // listener: require("../../../app/MainApp/config/listener.json"),
    endpoint: require("../../../app/MainApp/config/_endpoint.json"),
    // -- data-data yang besar
    sidenav: require("../../../app/MainApp/config/_sidenav.json"),
    sidenavOri: require("../../../app/MainApp/config/_sidenav.json"),
    customSidenav: require("../../../app/MainApp/config/sidenav.json"),
    customSidenavOri: require("../../../app/MainApp/config/sidenav.json"),
    packageLocal: require("../../../app/MainApp/config/_packageLocal.json"),
    packageLocalPerTenant: require("../../../app/MainApp/config/_packageLocalPerTenant.json"),
    package: require("../../../app/MainApp/config/package.json"),
    acl: require("../../../app/MainApp/config/_acl.json")
};

AppConfig.isModuleEnable = function(module) {
    return AppConfig.packageLocal[module] && AppConfig.packageLocal[module].enable;
};

// AppConfig['sidenavOri'] = JSON.parse(JSON.stringify(AppConfig.sidenav));

// jika multi tenant aktif maka replace packageLocal utama dengan packageLocalPerTenant
if(AppConfig.system.multitenant.active && typeof tenantId !== 'undefined' && AppConfig.packageLocalPerTenant[tenantId]){
    AppConfig.packageLocal = AppConfig.packageLocalPerTenant[tenantId];
}

export default AppConfig;
