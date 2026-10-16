import layoutHelpers from '@/helpers/layout.js';
import UserAuth from '@/helpers/userauth.js';
import Web from '@/helpers/web.js';
import PostRef from '@/helpers/postRef.js';
import Trans from '@/helpers/trans.js';
import AppConfig from '@/appconfig.js';
import Helper from '@/helpers/helper.js';
import Repo from '@/helpers/repo.js';

// import _default from 'vuex';

import { conformToMask } from 'node_modules/vue-text-mask';
import * as textMaskAddons from 'node_modules/text-mask-addons/dist/textMaskAddons';

let web = Web;
web.endpoint = AppConfig.endpoint;

/*
set local Api
*/
var localapi = new axios.create();
/**
 * parsing error local api
 * @param object res response axios
 **/
localapi.parseError = function (errResponse) {

    let err = {
        status: errResponse.status || 400,
        message: errResponse.message || "request error",
        errors: []
    };
    //jika error server
    if (!errResponse.data) {
        err.message = errResponse.message;
    } else {
        err.message = errResponse.data.message || err.message;
        err.status = errResponse.data.status || err.status;

        if (errResponse.data.errors) {
            err.errors = errResponse.data.errors;
            _.forEach(errResponse.data.errors, (v, i) => {
                if (v != true) {
                    if (v instanceof Object) {
                        _.forEach(v, (v2, i2) => {
                            err.message += "<br> - " + v2;
                        });
                    } else {
                        err.message += "<br> - " + v;
                    }
                }
            });
        }
    }

    return err;
};
localapi.errAlertText = {
    title: "Alert",
    text: "Session Expired"
};
localapi.showAllert = true;
localapi.isLoggingOut = false;
localapi.defaults.baseURL = "/";//AppConfig.client.endpoint[AppConfig.system.mode]["domain"];
localapi.defaults.headers.get["Accept"] = "application/json";
localapi.defaults.headers.common['Content-Type'] = 'multipart/form-data';
if (
    AppConfig.packageLocal.moduser != undefined
    && AppConfig.packageLocal.moduser.enable
    && AppConfig.system.has_auth
) {
    // Add Client Key Header
    window.axios.defaults.headers.common['X-Client-Key'] = AppConfig.client.api_key;
    localapi.defaults.headers.common['X-Client-Key'] = AppConfig.client.api_key;
    // console.log(AppConfig.client.api_key);
}
localapi.interceptors.response.use((response) => response, (error) => {
    if (!error.response) {
        throw error;
    }

    const err = localapi.parseError(error.response);
    const httpStatus = error.response.status;
    const responseStatus = error.response.data && error.response.data.status;
    const isExpiredSession = httpStatus === 401 && responseStatus !== 401001;

    if (
        isExpiredSession &&
        !localapi.isLoggingOut &&
        UserAuth.isActive() &&
        UserAuth.isLogin()
    ) {
        localapi.isLoggingOut = true;
        Web.showAlert({
            type: "warning",
            title: localapi.errAlertText.title,
            text: localapi.errAlertText.text
        });
        UserAuth.logoutAndReset();
    }

    if (httpStatus === 429 && (!error.response.data || !error.response.data.message)) {
        err.message = "Terlalu banyak permintaan. Silakan tunggu sebentar lalu coba kembali.";
    }

    err.errClass = error;
    throw err;
});

/*
jika multi tenant aktif
*/
// if(AppConfig.system.multitenant.active){
//     var newEndpoint = {};
//     _.forEach(AppConfig.endpoint.admin,(v,i)=>{
//         newEndpoint[i] = "/:group_app" + v;
//     });
//     AppConfig.endpoint.admin = newEndpoint;
// }

/**
 * Downloader
 */
let downloadVar = {
    path: ''
}

/**
 * Formater
 */
let formater = {}
/**
 * config format
 */
formater.config = {
    currencyMask: {
        prefix: 'Rp. ',
        allowDecimal: true,
        decimalSymbol: ',',
        thousandsSeparatorSymbol: '.',
        decimalLimit: 2,
        allowNegative: true
    },
    numberMask: {
        prefix: '',
        allowDecimal: false,
        thousandsSeparatorSymbol: '.',
        allowNegative: true
    },
    decimalMask: {
        prefix: '', allowDecimal: true,
        decimalSymbol: ',',
        thousandsSeparatorSymbol: '.',
        decimalLimit: 2,
        allowNegative: true
    },
    formatDate: 'DD-MM-YYYY'
};
/**
 * format config textmaskaddons nya
 */
formater.format = {
    setDecimalLimit(limit) {
        formater.config.currencyMask.decimalLimit = limit;
        formater.config.decimalMask.decimalLimit = limit;
        formater.format.currencyMask = textMaskAddons.createNumberMask(formater.config.currencyMask);
        formater.format.decimalMask = textMaskAddons.createNumberMask(formater.config.decimalMask);
    },
    setAllowNegative(allowNegative = true) {
        formater.config.currencyMask.allowNegative = allowNegative;
        formater.config.numberMask.allowNegative = allowNegative;
        formater.config.decimalMask.allowNegative = allowNegative;
        formater.format.currencyMask = textMaskAddons.createNumberMask(formater.config.currencyMask);
        formater.format.numberMask = textMaskAddons.createNumberMask(formater.config.numberMask);
        formater.format.decimalMask = textMaskAddons.createNumberMask(formater.config.decimalMask);
    },
    setThousandsSeparatorSymbol(simbol) {
        simbol = simbol == ',' ? ',' : '.';
        formater.config.currencyMask.thousandsSeparatorSymbol = simbol;
        formater.config.numberMask.thousandsSeparatorSymbol = simbol;
        formater.config.decimalMask.thousandsSeparatorSymbol = simbol;
        formater.format.currencyMask = textMaskAddons.createNumberMask(formater.config.currencyMask);
        formater.format.numberMask = textMaskAddons.createNumberMask(formater.config.numberMask);
        formater.format.decimalMask = textMaskAddons.createNumberMask(formater.config.decimalMask);
    },
    setDecimalSymbol(simbol) {
        simbol = simbol == ',' ? ',' : '.';
        formater.config.currencyMask.decimalSymbol = simbol;
        formater.config.decimalMask.decimalSymbol = simbol;
        formater.format.currencyMask = textMaskAddons.createNumberMask(formater.config.currencyMask);
        formater.format.decimalMask = textMaskAddons.createNumberMask(formater.config.decimalMask);
    },
    // -- set mask dengan global config
    currencyMask: textMaskAddons.createNumberMask(formater.config.currencyMask),
    numberMask: textMaskAddons.createNumberMask(formater.config.numberMask),//mask without decimal
    decimalMask: textMaskAddons.createNumberMask(formater.config.decimalMask),//mask with decimal
    // -- set mask dengan config tambahan/update an
    currencyMaskWithConfig: function (addsConfig = {}) {
        let config = _.merge(JSON.parse(JSON.stringify(formater.config.currencyMask)), addsConfig);
        return textMaskAddons.createNumberMask(config);
    },
    numberMaskWithConfig: function (addsConfig = {}) {
        let config = _.merge(JSON.parse(JSON.stringify(formater.config.numberMask)), addsConfig);
        return textMaskAddons.createNumberMask(config);
    },
    decimalMaskWithConfig: function (addsConfig = {}) {
        let config = _.merge(JSON.parse(JSON.stringify(formater.config.decimalMask)), addsConfig);
        return textMaskAddons.createNumberMask(config);
    },
};

/**
 * format function nya
 */

formater.formatCurrency = function (number, decimalLimit = 0) {
    if (isNaN(number)) number = formater.resetNumber(number);

    if (decimalLimit > 0)
        var config = formater.format.currencyMaskWithConfig({ decimalLimit: decimalLimit });

    if (formater.config.currencyMask.decimalSymbol == ',') {
        number = String(number);
        number = number.replace(/\./g, ',');
    } else {
        number = String(number);
    }
    return conformToMask(
        number,
        (typeof config !== 'undefined') ? config : formater.format.currencyMask,
        { guide: false }
    ).conformedValue;
};

formater.formatPrice = formater.formatCurrency;

formater.formatNumber = function (number) {
    if (isNaN(number)) number = formater.resetNumber(number);
    return conformToMask(
        String(number),
        formater.format.numberMask,
        { guide: false }
    ).conformedValue;
};

formater.formatDecimal = function (number, decimalLimit = 0) {
    if (isNaN(number)) number = formater.resetNumber(number);

    if (decimalLimit > 0)
        var config = formater.format.decimalMaskWithConfig({ decimalLimit: decimalLimit });

    if (formater.config.decimalMask.decimalSymbol == ',') {
        number = String(parseFloat(number));
        number = number.replace(/\./g, ',');
    } else {
        number = String(number);
    }
    return conformToMask(
        number,
        (typeof config !== 'undefined') ? config : formater.format.decimalMask,
        { guide: false }
    ).conformedValue;
};

formater.formatDate = function (dateString) {
    return moment(dateString).format(formater.config.formatDate);
};

formater.roundNumber = function (num, scale = 2) {
    return parseFloat(num).toFixed(scale);
    // if(!("" + num).includes("e")) {
    //   return +(Math.round(num + "e+" + scale)  + "e-" + scale);
    // } else {
    //   var arr = ("" + num).split("e");
    //   var sig = ""
    //   if(+arr[1] + scale > 0) {
    //     sig = "+";
    //   }
    //   return +(Math.round(+arr[0] + "e" + sig + (+arr[1] + scale)) + "e-" + scale);
    // }
};

formater.resetNumber = function (number) {
    if (number == 0) return 0;
    if (!(typeof number === 'string')) return number;
    number = String(number);
    number = number.replace(formater.config.currencyMask.prefix, "");
    number = number.replace(/[^0-9,.-]/g, "");

    if (formater.config.decimalMask.decimalSymbol == ',') {
        number = number.replace(/\./g, "").replace(/,/g, '.');
    } else {
        number = number.replace(/,/g, "");
    }
    return parseFloat(number);
};

// untuk detek apakah sudah diisi atau belum masked input saat get fokus
// untuk mengosongkan inputan agar mudah input
formater.maskedInputOnFocus = function (ev, emptyFormat) {
    if (ev.target.value == emptyFormat) {
        ev.target.value = '';
    } else {
        ev.target.select();
    }
}

// untuk detek apakah sudah diisi atau belum masked input saat lost fokus
// untuk diisi kembali dengan default format value kosong
formater.maskedInputOnBlur = function (ev, emptyFormat) {
    if (ev.target.value == '')
        ev.target.value = emptyFormat;
};

export default function () {
    return {
        // url domain utama + app path (jika ada)
        mainDomainAppUrl: AppConfig.system.multitenant.main_domain + AppConfig.endpoint.admin.app + '/',

        // Public url
        publicUrl: AppConfig.system.public_url ? AppConfig.system.public_url : '/',

        // Upload url
        uploadedUrl: AppConfig.system.uploaded_url ? AppConfig.system.uploaded_url : '/storage/',

        isOnTenantManager: isOnTenantManager,
        
        // Layout helpers
        layoutHelpers,

        //config app
        AppConfig,

        get RepoInit() {
            return Repo;
        },

        //auto vuex internal resource
        Repo: function (module) {
            return Repo.setModule(module);
        },


        //translation / locale
        Trans,

        //general helper
        Helper,

        //local api
        LocalApi: localapi,

        openProtectedFile: function (path, filename = 'attachment') {
            const token = UserAuth.getToken();

            web.setLoadingPage(true);

            return localapi.get(path, {
                responseType: 'blob',
                headers: {
                    Authorization: 'Bearer ' + token,
                    'Syn-Api-Token': token,
                },
            }).then(response => {
                const contentType = String(response.headers['content-type'] || '').toLowerCase();

                if (contentType.includes('application/json')) {
                    throw new Error('Server mengembalikan respons error, bukan file.');
                }

                const blob = response.data instanceof Blob
                    ? response.data
                    : new Blob([response.data], { type: contentType });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');

                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();

                // Firefox can cancel the download when the object URL is
                // revoked in the same event loop as link.click().
                window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
            }).catch(error => {
                const status = error.response ? error.response.status : null;
                const message = status === 401
                    ? 'Sesi login sudah tidak valid. Silakan login ulang lalu unduh kembali.'
                    : 'File gagal diunduh. Silakan coba kembali.';

                web.showAlert({ text: message, type: 'warning' });
            }).finally(() => {
                web.setLoadingPage(false);
            });
        },

        //user auth helper
        UserAuth,

        //formater
        Format: formater,
        moment: window.moment,

        //downloader
        download: function (path, filename) {
            let docUrl = this.downloadVar.path + path;
            let token = UserAuth.getToken();
            axios({
                method: 'get',
                url: docUrl,
                responseType: 'arraybuffer',
                headers: {
                    Authorization: "Bearer " + token,
                    "Syn-Api-Token": token
                }
            })
                .then(response => {
                    this.Web.setLoadingPage(false);
                    const url = window.URL.createObjectURL(new Blob([response.data]));
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', filename); //or any other extension
                    document.body.appendChild(link);
                    link.click();
                })
                .catch((err) => {
                    this.Web.setLoadingPage(false);
                    console.log('download error : ', err);
                    web.showAlert({ text: "Download Error", type: "warning" });
                });
        },
        downloadVar: downloadVar,
        setDownloadPath(path) {
            this.downloadVar.path = path;
        },

        //general web helper
        Web: web,

        PostRef,

        // Check for RTL layout
        get isRTL() {
            return document.documentElement.getAttribute('dir') === 'rtl' ||
                document.body.getAttribute('dir') === 'rtl'
        },

        // Check if IE
        get isIEMode() {
            return typeof document['documentMode'] === 'number'
        },

        // Check if IE10
        get isIE10Mode() {
            return this.isIEMode && document['documentMode'] === 10
        },

        // Layout navbar color
        get layoutNavbarBg() {
            return this.AppConfig.system.web_admin.navbar_bgcolor;
        },

        // Layout sidenav color
        get layoutSidenavBg() {
            return this.AppConfig.system.web_admin.sidenav_bgcolor;
        },

        // Layout footer color
        get layoutFooterBg() {
            return this.AppConfig.system.web_admin.footer_bgcolor;
        },


        // Animate scrollTop
        scrollTop(to, duration, element = document.scrollingElement || document.documentElement) {
            if (element.scrollTop === to) return
            const start = element.scrollTop
            const change = to - start
            const startDate = +new Date()

            // t = current time; b = start value; c = change in value; d = duration
            const easeInOutQuad = (t, b, c, d) => {
                t /= d / 2
                if (t < 1) return c / 2 * t * t + b
                t--
                return -c / 2 * (t * (t - 2) - 1) + b
            }

            const animateScroll = () => {
                const currentDate = +new Date()
                const currentTime = currentDate - startDate
                element.scrollTop = parseInt(easeInOutQuad(currentTime, start, change, duration))
                if (currentTime < duration) {
                    requestAnimationFrame(animateScroll)
                } else {
                    element.scrollTop = to
                }
            }

            animateScroll()
        }
    }
}
