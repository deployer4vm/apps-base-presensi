<template>
    <div>
            
        <div class="media align-items-center">
            <div class="ui-w-100 bg-light text-center rounded overflow-hidden">
                <a :href="localImagePath" target="_blank" v-if="!isImageEmpty"><img :src="localImagePath" style="max-width: 100px; max-height: 100px;" /></a>
                <img v-else :src="localImagePath" style="max-width: 100px; max-height: 100px;" />
            </div>
            <div class="media-body ml-3">
                <label class="form-label d-block mb-2">{{ fieldCaption }}</label>

                <b-btn variant="outline-primary" @click="$refs.FileInput.click()" size="sm" :disabled="disabled">Upload new photo</b-btn> &nbsp; <i class="text-muted" v-if="disabled">Untuk menambahkan avatar, silahkan tambahkan terlebih dahulu data user.</i>
                <b-btn variant="default md-btn-flat" @click="resetImage" size="sm" v-if="!isImageEmpty && deleteApi">{{ Trans.get("lang.reset") }}</b-btn>

                <div class="text-light small mt-1">Allowed JPG, GIF or PNG. Max size of 800K</div>
            </div>
        </div>
        <input
            ref="FileInput"
            type="file"
            style="display: none;"
            @change="onFileSelect"
        />
        
        <!-- <VueCropper
            v-show="!isImageEmpty"
            ref="cropper"
            :src="selectedFile"
            alt="Source Image"
        ></VueCropper>
        
        <b-modal size="md" scrollable centered id="crob-modals" 
            @ok="saveImage"
            >

            <div slot="modal-title">
                Crop Image
            </div>


            <template slot="modal-footer" slot-scope="{ ok, cancel }">
                <b-button size="sm" variant="primary" @click="ok()">
                    Ok
                </b-button>
                <b-button size="sm" variant="secondary" @click="cancel()">
                    Cancel
                </b-button>
            </template>
        </b-modal> -->
    </div>
</template>
<script>
// import { mapState } from "vuex";
// import axios from "axios";
// import VueCropper from "node_modules/vue-cropperjs";
// import "cropperjs/dist/cropper.css";

export default {
    name: "image-crop-upload",
    // components: { VueCropper },
    props: {
        imagePath: {},
        uploadApi: String,
        deleteApi: String,
        maxSize: {
            default() {
                return 0;
            }
        },
        disabled: {
            default() {
                return false;
            }
        },
        fieldCaption: {
            default() {
                return "Image";
            }
        },
        fieldName: {
            default() {
                return "image";
            }
        },
    },
    data() {
        return {
            isImageEmpty:true,
            localImagePath:"",
            cropedImage: "",
            autoCrop: false,
            selectedFile: "",
            image: "",
            dialog: false,
            files: "",
            defImage: "assets/images/default/default.png",
            // image detail
            mime: "",
            imageName: "",
            size: "",
        };
    },
    watch: {
        imagePath(){       
            if( typeof this.imagePath == 'string')     
            if(this.imagePath){
                this.localImagePath = this.publicUrl + 'upload/' + this.imagePath;            
                this.isImageEmpty = false;
            }else{
                this.localImagePath = this.publicUrl + this.defImage;
            }
        }
    },
    methods: {
        saveImage() {
            const userId = this.$route.params.user_id;
            this.cropedImage = this.$refs.cropper
                .getCroppedCanvas()
                .toDataURL();

            this.$refs.cropper.getCroppedCanvas().toBlob(blob => {
                this.uploadImage(blob);
            }, this.type);
        },
        uploadImage(image) {
            const formData = new FormData();
            formData.append(this.fieldName, image, this.name);
            this.globals()
                .LocalApi
                .post(uploadApi, formData,{
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                })
                .then(res => {
                    this.Web.showAlert({ 
                        title: this.Trans.get("alert.success_title"),
                        text: this.Trans.get("alert.update_success",{attribute:this.fieldCaption}), 
                        type: "success" 
                    });
                });
        },
        onFileSelect(e) {
            const file = e.target.files[0];
            this.selectedFile = file;

            this.mime = file.type;
            this.size = file.size;
            this.name = file.name;

            if(this.maxSize && this.size > this.maxSize){
                this.Web.showAlert({ 
                    title: this.Trans.get("alert.warning_title"),
                    text: "Ukuran file melebihi batas, maksimal " + parseInt(this.maxSize/100) + " Kb", 
                    type: "warning" 
                });
                return false;
            }

            if (typeof FileReader === "function") {
                const reader = new FileReader();
                reader.onload = event => {
                    // this.selectedFile = event.target.result;
                    // // this.$bvModal.show("crob-modals");
                    // this.$refs.cropper.replace(event.target.result);
                    this.localImagePath = event.target.result;                    
                    this.$emit("setValue", e.target.files[0]);
                    // jika set upload api maka saat set langsung upload (khusus jika cropper belum jalan)
                    // if(this.uploadApi)
                    //     this.uploadImage(event.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                alert("Sorry, FileReader API not supported");
            }
        },
        resetImage() {
            this.Web.showAlert({
                styleType: "modal",
                type: "warning",
                title: this.Trans.get("alert.delete_confirm_title"),
                text: 'Hapus ' + this.fieldCaption + ' ?',
                modalButtonCancel: this.Trans.get("lang.no"),
                modalButtonOk: this.Trans.get("lang.yes"),
                onOk: () => {
                    this.globals()
                    .LocalApi.delete(deleteApi)
                    .then(res => {
                        this.localImagePath = this.defImage;
                        return true;
                    });
                }
            });
        }
    },
    created() {
        if(this.imagePath){
            this.localImagePath = this.publicUrl + 'upload/' + this.imagePath;            
            this.isImageEmpty = false;
        }else{
            this.localImagePath = this.publicUrl + this.defImage;
        }
        
        this.selectedFile = this.publicUrl + 'upload/' + this.localImagePath;
    }
};
</script>