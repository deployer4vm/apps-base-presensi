/**
 * General global function this.Helper / globals().Helper
 */
export default {
    round(num) {
        return Math.round((num + Number.EPSILON) * 100) / 100;
    },
    roundNumber(value, decimals = 2) {
        // console.log(Number(value).toPrecision(decimals),value,decimals);
        return Number(value).toFixed(decimals);
        // value = Number(value);
        // console.log(Number(Math.round(value+'e'+decimals)+'e-'+decimals),value);
        // return Number(Math.round(value+'e'+decimals)+'e-'+decimals);
        // if(!("" + num).includes("e")) {
        //     return +(Math.round(num + "e+" + scale)  + "e-" + scale);
        // } else {
        //     var arr = ("" + num).split("e");
        //     var sig = ""
        //     if(+arr[1] + scale > 0) {
        //         sig = "+";
        //     }
        //     return +(Math.round(+arr[0] + "e" + sig + (+arr[1] + scale)) + "e-" + scale);
        // }
    },

    countDecimals(value) {
        if (typeof value == 'undefined') return 0;
        if (Math.floor(value) !== value)
            return value.toString().split(".")[1].length || 0;
        return 0;
    },
    /**
     * convert array to query string
     **/
    params(object) {
        var parameters = [];
        for (var property in object) {
            if (object.hasOwnProperty(property)) {
                parameters.push(encodeURI(property + '=' + object[property]));
            }
        }

        return parameters.join('&');
    },
    //convert array (5 level) to FormData object
    convertToFormData(form) {
        var formData = new FormData();

        var loopingAdd = function (data, key) {
            _.forEach(data, (v, k) => {
                if (v instanceof Object && !(v instanceof String)) {
                    if (v instanceof File || v == []) {
                        formData.append(key + '[' + k + ']', v);
                    } else if (v == [] || (v.length != undefined && v.length == 0)) {
                        formData.append(key + '[' + k + ']', '');
                    } else {
                        loopingAdd(v, key + '[' + k + ']');
                    }
                } else {
                    if (v != null)
                        formData.append(key + '[' + k + ']', v);
                }
            });
        };

        _.forEach(form, (v, k) => {
            if (v instanceof Object && !(v instanceof String)) {
                if (v instanceof File || v == []) {
                    formData.append(k, v);
                } else if (v == [] || (v.length != undefined && v.length == 0)) {
                    formData.append(k, '');
                } else {
                    loopingAdd(v, k);
                }
            } else {
                if (v != null)
                    formData.append(k, v);
            }
        });

        return formData;

        // looping level 1
        // _.forEach(form,(v,k)=>{
        //     if(v instanceof Object && !(v instanceof String)){
        //         if(v instanceof File){
        //             formData.append(k, v);
        //         }else{
        //             // looping level 2
        //             _.forEach(v,(v2,k2)=>{
        //                 if(v2 instanceof Object && !(v2 instanceof String)){
        //                     if(v2 instanceof File){
        //                         formData.append(k+'['+k2+']', v2);
        //                     }else{
        //                         // looping level 3
        //                         _.forEach(v2,(v3,k3)=>{
        //                             if(v3 instanceof Object && !(v3 instanceof String)){
        //                                 if(v3 instanceof File){

        //                                     formData.append(k+'['+k2+']'+'['+k3+']', v3);
        //                                 }else{
        //                                     // looping level 4
        //                                     _.forEach(v3,(v4,k4)=>{
        //                                         if(v4 instanceof Object && !(v4 instanceof String)){
        //                                             if(v4 instanceof File){

        //                                                 formData.append(k+'['+k2+']'+'['+k3+']'+'['+k4+']', v4);
        //                                             }else{
        //                                                 // looping level 5
        //                                                 _.forEach(v4,(v5,k5)=>{



        //                                                     if(v5 instanceof Object && !(v5 instanceof String)){
        //                                                         if(v5 instanceof File){

        //                                                             formData.append(k+'['+k2+']'+'['+k3+']'+'['+k4+']'+'['+k5+']', v5);
        //                                                         }else{
        //                                                             // looping level 6
        //                                                             _.forEach(v5,(v6,k6)=>{
        //                                                                 if(v5!=null)
        //                                                                     formData.append(k+'['+k2+']'+'['+k3+']'+'['+k4+']'+'['+k5+']'+'['+k6+']', v6);
        //                                                             });
        //                                                         }
        //                                                     }else{
        //                                                         if(v5!=null)
        //                                                             formData.append(k+'['+k2+']'+'['+k3+']'+'['+k4+']'+'['+k5+']', v5);
        //                                                     }

        //                                                 });
        //                                             }
        //                                         }else{
        //                                             if(v4!=null)
        //                                                 formData.append(k+'['+k2+']'+'['+k3+']'+'['+k4+']', v4);
        //                                         }

        //                                     });
        //                                 }
        //                             }else{
        //                                 if(v3!=null)
        //                                     formData.append(k+'['+k2+']'+'['+k3+']', v3);
        //                             }
        //                         });
        //                     }
        //                 }else{
        //                     if(v2!=null)
        //                         formData.append(k+'['+k2+']', v2);
        //                 }

        //             });

        //         }
        //     }else{
        //         if(v!=null)
        //             formData.append(k, v);
        //     }
        // });
        // return formData;

    },
    isString(x) {
        return Object.prototype.toString.call(x) === "[object String]"
    },
    /**
     *
     * @param {*} stringVal
     * @param {*} replaceObj
     * @returns
     */
    replaceAttribute(stringVal, replaceObj = {}) {
        _.forEach(replaceObj, (v, k) => {
            // if(this.isString(v)){
            stringVal = stringVal.replace(":" + k, v);
            // }
        });
        return stringVal;
    }
}
