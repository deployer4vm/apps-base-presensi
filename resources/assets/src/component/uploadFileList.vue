<template>
    <div>
        <!-- <div class="d-flex justify-content-between px-2 pt-2 mb-0">
            <div>
                <b>{{ title }}</b>
                <i
                    >(<small>File type : {{ extensions }}</small
                    >)</i
                >
            </div>

            <b-dd v-if="!disabled" size="sm" split :right="isRTL" @click="$refs[refID].$el.querySelector('input').click()">
                <template slot="button-content"> <i class="ion ion-md-add"></i> Add Files </template>
                <b-dd-item v-if="multiple" @click="onAddFolder">Add folder</b-dd-item>
            </b-dd>
        </div> -->

        <div class="box-preview">
            <div class="preview-desc" v-if="!intFiles.length">
                <b-row>
                    <b-col md="12">
                        <div class="preupload-img" v-if="!disabled">
                            <div class="box-img">
                                <h5>
                                    {{ dropFilesCaption }}
                                    <div class="text-muted small my-2">or</div>
                                </h5>
                                <div
                                    @click="$refs[refID].$el.querySelector('input').click()"
                                    class="btn btn-outline-primary w-icon btn-xs"
                                    >
                                    <i class="fi fi-rs-upload"></i>
                                    <span>Select File</span>
                                </div>
                            </div>
                        </div>
                        <div v-else>No Files</div>
                    </b-col>
                </b-row>
            </div>
            <div class="preview-desc"
                v-for="(file, index) in intFiles"
                :key="file.id">
                <b-row>
                    <b-col md="5">
                        <div class="preupload-img">
                            <div class="box-img">
                                <img
                                    v-if="file.thumb"
                                    :src="file.thumb"
                                />
                                <i v-else>[No Image]</i>
                            </div>
                        </div>
                    </b-col>
                    <b-col md="7">
                        <div class="preupload-desc">
                            <ul>
                                <li>
                                    <span>Nama</span>
                                    <span>
                                        <a
                                            v-b-tooltip.hover.top
                                            :title="'View file'"
                                            :href="intUploadUrl + file.filepath"
                                            target="_blank"
                                            >{{ file.name }}
                                            </a>
                                    </span>
                                </li>
                                <li>
                                    <span>Size</span>
                                    <span>
                                        {{ (file.size / 1024 / 1024) | fileSize }} MB
                                    </span>
                                </li>
                                <li v-if="!disabled">
                                    <span>Action</span>
                                    <span>
                                        <b-btn
                                            @click="imageDelete(index)"
                                            variant="outline-danger w-icon btn-xs"
                                            title="Delete Image"
                                        >
                                            <i
                                                class="fi fi-rs-trash"
                                                aria-hidden="true"
                                            ></i>
                                            <span>Delete</span>
                                        </b-btn>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </b-col>
                </b-row>

            </div>
        </div>
        <div>
            <b>{{ title }}</b>
            <i
                >(<small>File type : {{ extensions }}</small
                >)</i
            >
        </div>

        <file-upload
            class="sr-only position-absolute"
            :name="inputFileName"
            :data="inputFileData"
            :post-action="postAction"
            :extensions="extensions"
            :accept="accept"
            :headers="uploadHeaders"
            :multiple="multiple"
            :directory="directory"
            :size="size || 0"
            :thread="thread < 1 ? 1 : thread > 5 ? 5 : thread"
            :drop="drop"
            :drop-directory="dropDirectory"
            :add-index="addIndex"
            v-model="intFiles"
            @input-filter="inputFilter"
            @input-file="inputFile"
            :ref="refID"
        />

        <!-- <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Thumb</th>
                        <th>Name</th>
                        <th>Size</th>
                        <th v-if="hasDesc">Deskripsi</th>
                        <th>Speed</th>
                        <th>Status</th>
                        <th v-if="!disabled">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!intFiles.length">
                        <td colspan="7">
                            <div class="text-center p-2" v-if="!disabled">
                                <h4>
                                    {{ dropFilesCaption }}
                                    <div class="text-muted small my-3">or</div>
                                </h4>
                                <label
                                    @click="
                                        $refs[refID].$el
                                            .querySelector('input')
                                            .click()
                                    "
                                    class="btn btn-primary btn-xs"
                                    >Select Files</label
                                >
                            </div>
                            <div v-else>No Files</div>
                        </td>
                    </tr>
                    <tr
                        v-for="(file, index) in intFiles"
                        :key="file.id"
                        :class="{
                            'table-danger':
                                index == 0 && firstFileAsCover ? true : false,
                        }"
                    >
                        <td>{{ index + 1 }}</td>
                        <td>
                            <img
                                v-if="file.thumb"
                                :src="file.thumb"
                                width="80"
                                height="auto"
                            />
                            <i v-else>[No Image]</i>
                        </td>
                        <td>
                            <div class="filename">
                                <a
                                    v-b-tooltip.hover.top
                                    :title="'View file'"
                                    :href="intUploadUrl + file.filepath"
                                    target="_blank"
                                    >{{ file.name }}
                                    <span class="ml-2 ion ion-md-open"></span
                                ></a>
                                <div v-if="firstFileAsCover && index == 0">
                                    <b>[COVER IMAGE]</b>
                                </div>
                            </div>
                            <b-progress
                                :value="Number(file.progress)"
                                :variant="file.error ? 'danger' : ''"
                                :animated="file.active"
                                v-if="file.active || file.progress !== '0.00'"
                                height="6px"
                                style="margin: 4px 0 0 0"
                            />
                        </td>
                        <td>{{ (file.size / 1024 / 1024) | fileSize }} MB</td>
                        <td v-if="hasDesc">
                            <b-textarea
                                v-model.trim="intFiles[index].desc"
                                placeholder="Keterangan"
                                @change="updateFiles"
                                :disabled="disabled"
                            />
                        </td>
                        <td>{{ file.speed }}</td>

                        <td v-if="file.error">{{ file.error }}</td>
                        <td v-else-if="file.success">Uploaded File</td>
                        <td v-else-if="file.active">active</td>
                        <td v-else>New File</td>

                        <td v-if="!disabled">
                            <template v-if="canMovePos">
                                <b-btn
                                    @click="imageMoveUp(index)"
                                    variant="info btn-xs"
                                    title="Move Up"
                                >
                                    <i
                                        class="ion ion-ios-arrow-up"
                                        aria-hidden="true"
                                    ></i>
                                </b-btn>
                                <b-btn
                                    @click="imageMoveDown(index)"
                                    variant="info btn-xs"
                                    title="Move Down"
                                >
                                    <i
                                        class="ion ion-ios-arrow-down"
                                        aria-hidden="true"
                                    ></i>
                                </b-btn>
                            </template>
                            <b-btn
                                @click="imageDelete(index)"
                                variant="danger btn-xs"
                                title="Delete Image"
                            >
                                <i
                                    class="ion ion-md-close"
                                    aria-hidden="true"
                                ></i>
                            </b-btn>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div> -->
    </div>
</template>
<script>
import VueUploadComponent from "node_modules/vue-upload-component";

Vue.filter("fileSize", function (value) {
    if (!value) return "";
    const parts = String(value).split(".");
    return `${parts[0]}.${parts[1].slice(0, 2)}`;
});

export default {
    name: "upload-file-list",
    components: {
        FileUpload: VueUploadComponent,
    },
    props: {
        title: {
            default() {
                return "File Attachment";
            },
        },
        dropFilesCaption: {
            default() {
                return "Drop files anywhere to upload";
            },
        },
        files: {
            default() {
                return [];
            },
        },
        disabled: {
            default() {
                return false;
            },
        },
        firstFileAsCover: {
            default() {
                return true;
            },
        },
        canMovePos: {
            default() {
                return true;
            },
        },
        hasDesc: {
            default() {
                return true;
            },
        },
        accept: {
            default() {
                return "image/png,image/gif,image/jpeg";
            },
        },
        refID: {
            default() {
                return "upload";
            },
        },
        extensions: {
            default() {
                return "gif,jpg,jpeg,png";
            },
        },
        inputFileName: {
            default() {
                return "file";
            },
        },
        minSize: {
            default() {
                return 1024;
            },
        },
        size: {
            default() {
                return 1024 * 1024 * 10;
            },
        },
        uploadUrl: {
            default() {
                return false;
            },
        },
        multiple: {
            default() {
                return true;
            },
        },
    },
    watch: {
        files(v) {
            if (this.dataNotLoaded) this.loadFiles();
        },
    },
    data: () => ({
        dataNotLoaded: true,

        intUploadUrl: "",
        //
        // Vue Upload Component
        //

        intFiles: [], //list file
        // newFiles: [],//list file baru yg diupload
        // deletedFiles: [],//list file lama yg didelete
        // fileCount: 0,
        // inputFileName: "file_attachment", --> move to props
        inputFileData: {}, //data imput tambahan
        // accept: "image/png,image/gif,image/jpeg", --> move to props
        // extensions: "gif,jpg,jpeg,png", --> move to props

        // extensions: ['gif', 'jpg', 'jpeg','png', 'webp'],
        // extensions: /\.(gif|jpe?g|png|webp)$/i,

        // minSize: 1024, --> move to props
        // size: 1024 * 1024 * 10, --> move to props
        // multiple: true, --> move to props
        directory: false,
        drop: true,
        dropDirectory: true,
        addIndex: false,
        thread: 3,
        apiPostAction: "",
        postAction: "",
        uploadHeaders: {},
    }),
    methods: {
        updateFiles() {
            this.dataNotLoaded = false;
            var newFiles = [];
            _.forEach(this.intFiles, (v, k) => {
                newFiles.push({
                    filepath: v.file ? v.file : v.filepath,
                    name: v.name,
                    size: v.size,
                    desc: v.desc,
                });
            });
            this.$emit("onFiles", newFiles);
        },
        loadFiles() {
            this.intUploadUrl = this.uploadUrl
                ? this.uploadUrl
                : this.uploadedUrl;
            _.forEach(this.files, (v, k) => {
                this.intFiles.push({
                    id: v.filepath,
                    name: v.name,
                    thumb: this.intUploadUrl + v.filepath,
                    filepath: v.filepath,
                    size: v.size,
                    desc: v.desc ? v.desc : "",
                    success: true,
                    response: { id: k },
                });
            });
            this.dataNotLoaded = false;
        },
        /**
         * VUE FILE UPLOAD
         * ====================================
         */
        imageMoveUp(index) {
            this.intFiles.move(index - 1, index);
            this.updateFiles();
        },
        imageMoveDown(index) {
            this.intFiles.move(index, index + 1);
            this.updateFiles();
        },
        imageDelete(index) {
            this.intFiles.splice(index, 1);
            this.updateFiles();
        },
        inputFilter(newFile, oldFile, prevent) {
            if (newFile && !oldFile) {
                // Before adding a file
                // Filter system intFiles or hide intFiles
                if (
                    /(\/|^)(Thumbs\.db|desktop\.ini|\..+)$/.test(newFile.name)
                ) {
                    return prevent();
                }
                // Filter php html js file
                if (/\.(php5?|html?|jsx?)$/i.test(newFile.name)) {
                    return prevent();
                }
            }
            if (newFile && (!oldFile || newFile.file !== oldFile.file)) {
                // Create a blob field
                newFile.blob = "";
                let URL = window.URL || window.webkitURL;
                if (URL && URL.createObjectURL) {
                    newFile.blob = URL.createObjectURL(newFile.file);
                }
                // Thumbnails
                newFile.thumb = "";
                if (newFile.blob && newFile.type.substr(0, 6) === "image/") {
                    newFile.thumb = newFile.blob;
                }
            }
        },
        // add, update, remove File Event
        inputFile(newFile, oldFile) {
            // update
            if (newFile && oldFile) {
                if (newFile.active && !oldFile.active) {
                    // beforeSend
                    // min size
                    if (
                        newFile.size >= 0 &&
                        this.minSize > 0 &&
                        newFile.size < this.minSize
                    ) {
                        this.$refs[this.refID].update(newFile, {
                            error: "size",
                        });
                    }
                }
            }
            this.updateFiles();
        },
        // add folader
        onAddFolder() {
            if (!this.$refs[this.refID].features.directory) {
                alert("Your browser does not support");
                return;
            }
            let input = this.$refs[this.refID].$el.querySelector("input");
            input.directory = true;
            input.webkitdirectory = true;
            this.directory = true;
            input.onclick = null;
            input.click();
            input.onclick = (e) => {
                this.directory = false;
                input.directory = false;
                input.webkitdirectory = false;
            };
        },
    },
    created() {
        if (this.files.length > 0) this.loadFiles();
    },
};
</script>
