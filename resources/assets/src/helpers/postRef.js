import globals from "@/globals";

/**
 * Diakses via this.PostRef di vue instance atau via globals (globals().PostRef).
 */
export default {
    getPostRef(formId) {
        return globals()
            .LocalApi.get(
                globals().Web.getEndpoint(globals().AppConfig.endpoint.api.app)
                    + (globals().AppConfig.system.post_ref_endpoint
                        ? globals().AppConfig.system.post_ref_endpoint
                        : '/sys/postref'),
                {
                    params: {
                        form_id: formId
                    }
                }
            ).then((val) => {
                return val.data.data;
            }).catch((err) => {
                console.log('getPostRef error.');
                return false;
            });
    }
}
