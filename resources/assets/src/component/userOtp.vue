<template>
    <div>
        <b-modal 
            id="modals-user-otp-input-otp" 
            @ok="submitOtp" 
            modal-class="modal-fill-in modal-kiosk"
            footer-class="p-0"
            size="md"
            hide-footer 
            no-close-on-backdrop 
            no-close-on-esc
        >
            <b-container>
                <!-- otp form description -->
                <p v-html="Trans.get('auth.form_otp.desc',{channel: channel, timeout: timeout})"></p>
                <!-- OTP timeout countdown -->
                <h3 class="text-center font-weight-normal text-danger">
                    {{formatTime(countdown)}}
                </h3>
                <!-- Expired OTP resend form -->
                <div v-if="otpExpired" class="text-center bg-light m-3 p-2">
                    {{Trans.get('auth.form_otp.otp_hangus_desc')}}
                    <b-btn @click="resendOtp" variant="outline-success" class="ml-2 btn-outline-success btn-sm w-icon">
                        <i class="ion ion-ios-send mr-2"></i> {{Trans.get('auth.form_otp.btn_otp_resend_otp')}}
                    </b-btn>
                </div>
                <!-- Caption -->
                <h5 class="my-2 text-center">
                    {{Trans.get('auth.form_otp.input_otp')}}
                </h5>
                <!-- OTP input form -->
                <b-form-group class="d-flex justify-content-center">
                    <b-form-input :disabled="otpExpired" :maxlength="authConfig.otp.digit" v-model.trim="otp" type="password" 
                        class="form-control form-control-lg form-control-alt" style="width: 360px;" @keyup.enter="submitOtp" autofocus></b-form-input>
                </b-form-group>
                <div class="text-center">
                    <b-btn :disabled="otpExpired" @click="submitOtp" class="btn btn-lg btn-gradient key enter" variant="gradient">
                        {{Trans.get('lang.submit')}}
                    </b-btn>
                </div>
            </b-container>
        </b-modal>
    </div>
</template>

<script>
export default {
    name: "user-otp",
    props: {
        showForm: {
            default() {
                return true;
            },
        },
        hideForm: {
            default() {
                return true;
            },
        },
    },
    data: () => ({
        otp:'',
        showOtpForm: true,
        hideOtpForm: true,
        otpTimeout: null,
        otpExpired: false,
        //
        countdown: 0,
        timer: null,
    }),
    watch: {
        showForm(v) {
            this.sendOtp().then((res) => {
                if(res){
                    this.otp = '';
                    this.$bvModal.show("modals-user-otp-input-otp");
                }
            });
        },
        hideForm(v) {
            this.Web.setLoadingPage(false);
            this.$bvModal.hide("modals-user-otp-input-otp");
        },
    },
    computed: {
        authConfig() {
            return this.$store.state.authConfig.dataConfig;
        },
        channel() {
            return this.Trans.get("user.field_caption.otp_channel_select_" + this.UserAuth.getUser('otp_channel'));
        },
        timeout() {
            return this.authConfig.otp.timeout;
        }
    },
    methods: {      
        resendOtp() {
            this.sendOtp().then((res) => {
                this.Web.showAlert({ text: "kode OTP telah dikirim ulang" });
            });
        },
        sendOtp() {
            this.Web.setLoadingPage(true);
            return this.LocalApi.post(this.AppConfig.endpoint.api.moduser + "/send-otp")
                .then((res) => {
                    this.otpTimeout = res.data.data.timeout;
                    this.countdown = this.calculateCountdown();
                    this.otpExpired = false;
                    this.startCountdown();
                    this.Web.setLoadingPage(false);
                    return true;
                })
                .catch((res) => {
                    this.Web.showAlert({
                        title: this.Trans.get("alert.warning_title"),
                        text: res.message,
                        type: "danger",
                    });
                    this.Web.setLoadingPage(false);
                    this.$bvModal.hide("modals-user-otp-input-otp");
                    return false;
                });
        },
        submitOtp() {
            this.$emit("submitOtp",this.otp);
        },
        // countdown
        calculateCountdown() {
            const targetTime = new Date(this.otpTimeout).getTime();
            const currentTime = Date.now();
            return Math.max(Math.floor((targetTime - currentTime) / 1000), 0);
        },
        formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
        },
        startCountdown() {
            this.timer = setInterval(() => {
                if (this.countdown > 0) {
                    this.countdown--;
                } else {
                    clearInterval(this.timer);
                    this.otpExpired = true;
                }
            }, 1000); // Update every second
        },
    }
}
</script>