var template = `
<div>
    <!-- tampilkan form upload jika statusnya 0 (bisa upload baru)  -->
    <template v-if="importStatus.status==0">
        <form :action="apiImportUpload" method="post">
            <div class="form-group row">
                <label class="col-form-label col-sm-2 text-sm-right">Import </label>
                <div class="col-sm-8">
                    <input 
                        type="file" 
                        accept="application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" 
                        class="form-control" name="importFile" 
                        ref="file" 
                        v-on:change="handleFileUpload()" 
                        :disabled="onImportProcess">
                </div>
            </div>

            <div class="form-group row" v-if="showTransactionDate">
                <label class="col-form-label col-sm-2 text-sm-right" v-text="transactionDateCaption"></label>
                <div class="col-sm-8">
                    <input 
                        type="text" 
                        class="form-control datepicker-base transaction-date" 
                        :placeholder="transactionDateCaption" 
                        name="transactionDate" 
                        v-model="importForm.transactionDate" 
                        :disabled="onImportProcess">
                </div>
            </div>
            
            <div class="form-group row" v-if="form.importFile && !onImportProcess">
                <label class="col-form-label col-sm-2 text-sm-right"></label>
                <div class="col-sm-8">
                    <button @click="uploadImpoart" class="btn btn-success">Upload</button>
                </div>
            </div>
        </form>
    </template>
    <!-- saat baru mulai -->
    <div v-else-if="importStatus.status==1" class="alert alert-success show d-inline-blcok">
        <i>Uploading...</i>
    </div>   
    <!-- jika sedang dalam proses import -->
    <div 
        v-else-if="importStatus.status==2"
        class="alert alert-success show d-inline-blcok pr-0" 
        style="max-height: 300px; overflow-x: auto;">
        <i>Import data sedang diproses, mohon tunggu...</i> - 
        <b>
            Data count : <span v-text="importStatus.count"></span></b> - <b>Processed count : <span v-text="importStatus.processedCount"></span>
        </b>                              
        <div v-html="importStatus.log" class="p-1" style="background: rgba(0,0,0,0.1); max-height: 190px; overflow-x: auto;"></div>                        
    </div>   
    <!-- jika status selesai atau gagal import maka tampilkan tombol approve dan/atau reset -->
    <template v-else>
        <!-- jika sedang tidak dalam on progress cancle dan on progress import -->
        <template v-if="importStatus.status!=5 && importStatus.status!=6">
            <button @click="approveImport" class="btn btn-success btn-md mb-4" :disabled="importStatus.status==4">
                <span class="ion ion-md-checkmark-circle"></span>&nbsp; Approve Import
            </button>
            <button @click="cancelImport" class="btn btn-danger btn-md mb-4">
                <span class="ion ion-md-close-circle"></span>&nbsp; Cancel Import
            </button>
        </template>
        
        <div class="alert-info alert show pr-0" style="max-height: 200px; overflow-x: auto;">
            <i>Import data selesai :</i>                            
            <div v-html="importStatus.log" class="p-1" style="background: rgba(0,0,0,0.1); max-height: 135px; overflow-x: auto;"></div>
        </div>
    </template>
</div>
`;
var cImport = Vue.component("c-import", {
    template: template,
    props: {
        "api-import-upload": {},
        "api-import-approve": {},
        "api-import-status": {},
        "api-import-cancel": {},
        "last-transaction-date": {
            type: String,
            default: moment().format('YYYY-MM-DD')
        },
        "transaction-date-caption": {
            type: String,
            default: 'Date'
        },
        "addsparam": {},
        "show-transaction-date": {},
        "on-start": {},
        "on-get-status": {},
        "on-finish": {},
        "on-fail": {},
        "on-approve": {},
        "on-cancel": {},
        "on-approve-finish": {},
        "on-cancel-finish": {}
    },
    $_veeValidate: {
        validator: "new"
    },
    data() {
        return { 
            onImportProcess: false, //flag untuk detek apakah sedang proses upload ? agar tidak bisa klik berkali-kali           
            form: {
                importFile: null,
                transactionDate: null
            },
            importStatus: {
                status: 0,//status import, 0 sedang tidak ada proses, 1 sudah ada tapi belum start, 2 sedang dalam proses, 3 done, 4 failed
                log: '',//text log  
                filename: '',
                filenamePath: '',
                count:0,
                processedCount:0
            },
            lastDate: null
        };
    },
    created() {
        this.form.transactionDate = moment().format('YYYY-MM-DD');
        if(this.lastTransactionDate){
            this.lastDate = this.lastTransactionDate;
        }else{
            this.lastDate = moment().format('YYYY-MM-DD');
        }
        if(!this.transactionDateCaption) this.transactionDateCaption = 'Tanggal Transaksi';
        this.showTransactionDate = this.showTransactionDate||this.showTransactionDate==undefined?true:false;

        this.getImportStatus(true);        
    },
    mounted: function() {  
        var that = this;    
        
        if(this.Web==undefined){                 
            $('.datepicker-base.transaction-date').datepicker({
                format: 'yyyy-mm-dd',
                startDate: this.lastDate,
                endDate: moment().format('YYYY-MM-DD'), 
                autoclose: true
            });
            $('.datepicker-base.transaction-date').change(function(v){
                that.form.transactionDate = $(this).val();
            });
        }
    },
    methods: {
        showAlert(params)
        {
            if(this.Web==undefined){
                showAlert(params);
            }else{
                this.Web.showAlert(params);
            }
        },
        handleFileUpload(){
            this.form.importFile = this.$refs.file.files[0];
        },
        uploadImpoart(ev) {
            ev.preventDefault();
            if(this.onImportProcess)return false;
            var that = this;
            var formData = new FormData();
            formData.append('importFile', this.form.importFile);
            formData.append('transactionDate', moment(this.form.transactionDate).format('YYYY-MM-DD'));
            this.onImportProcess = true;
            axios.post(this.apiImportUpload, formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'        
                    }
                }
            )
            .then(res => {
                this.onImportProcess = false;
                // var lastStatus = this.importStatus.status;
                this.importStatus = res.data.data;
                this.onStart(this.importStatus);
                this.showAlert({text: "File import berhasil diupload dan sedang diproses, silahkan tunggu hingga proses import selesai",type: "success"});
                setTimeout(function() {
                    that.getImportStatus();
                },1000); 
            }).catch((res)=>{
                this.onImportProcess = false;
                this.showAlert({text: "Upload file import gagal : " + res.message,type: "warning"});
            });
        },
        //get status terakhir import
        getImportStatus(firstLoad=false){
            var that = this;
            axios.get( this.apiImportStatus)
                .then((res)=>{
                    var oldStatus = JSON.parse(JSON.stringify(this.importStatus));
                    this.importStatus = res.data.data;
                    this.onGetStatus(this.importStatus);

                    //jika belum selesai atau baru mulai upload, maka request status lagi nanti
                    if(this.importStatus.status==1||this.importStatus.status==2||this.importStatus.status==5||this.importStatus.status==6){
                        setTimeout(function() {
                            that.getImportStatus();
                        },1000); 
                    //jika import selesai dan berhasil                          
                    }else if(this.importStatus.status==3 && !firstLoad){
                        if(this.onFinish!=undefined)
                            this.onFinish(this.importStatus);
                        this.showAlert({text: "Proses import selesai.",type: "success"});
                    //jika import selesai dan gagal
                    }else if(this.importStatus.status==4 && !firstLoad){
                        if(this.onFail!=undefined)
                            this.onFail(this.importStatus);
                        this.showAlert({text: "Proses import gagal.",type: "danger"});
                    //jika approve import selesai dan berhasil                          
                    }else if(this.importStatus.status==0 && oldStatus.status == 5 && !firstLoad){
                        if(this.onApproveFinish!=undefined)
                            this.onApproveFinish(this.importStatus);
                        this.importStatus = oldStatus;
                        this.showAlert({text: "Proses Approve selesai.",type: "success"});
                    //jika pembatalan import selesai dan berhasil                          
                    }else if(this.importStatus.status==0 && oldStatus.status == 6 && !firstLoad){
                        if(this.onCancelFinish!=undefined)
                            this.onCancelFinish(this.importStatus);
                        this.importStatus = oldStatus;
                        this.showAlert({text: "Proses pembatalan selesai.",type: "success"});
                    //jika status 0 berarti sudah tidak ada proses
                    }else{
                        this.form.importFile = null;
                    }                      
                }).catch((res)=>{
                    this.showAlert({text: "Access status import gagal : " + res.message,type: "warning"});
                });
        },
        approveImport(){
            var that = this;
            axios.post( this.apiImportApprove)
                .then((res)=>{
                    this.importStatus = res.data.data;
                    if(this.onApprove!=undefined)
                        this.onApprove(this.importStatus);
                    this.showAlert({text: "Data import diapprove.",type: "success"});
                    setTimeout(function() {
                        that.getImportStatus();
                    },1000);
                }).catch((res)=>{
                    this.showAlert({text: "Request approve gagal : " + res.message,type: "warning"});
                });
        },
        cancelImport(){
            var that = this;
            axios.delete( this.apiImportCancel)
                .then((res)=>{
                    this.importStatus = res.data.data;
                    if(this.onCancel!=undefined)
                        this.onCancel(this.importStatus);
                    this.showAlert({text: "Data import dibatalkan.",type: "success"});
                    setTimeout(function() {
                        that.getImportStatus();
                    },1000);
                }).catch((res)=>{
                    this.showAlert({text: "Request pembatalan gagal : " + res.message,type: "warning"});
                });
        }
    }
});
