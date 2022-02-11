<template>
    <div id="merck-scraper-log" class="merck-scraper-log mt-4 mb-4">
        <div class="merck-scraper-log__filters d-flex justify-content-lg-between mb-4">
            <div class="button-group">
                <Button :title="buttonAtts.title"
                        :btn-class="buttonAtts.btnClass"
                        :btn-id="buttonAtts.btnId"
                        :type="buttonAtts.type"
                        :emit-event="refreshContent" />
            </div>
            <select class="form-select"
                    :disabled="selectDisabled"
                    :aria-disabled="selectDisabled"
                    v-model="dirSelect">
                <option disabled
                        selected
                        value="default">Select a log directory</option>
                <option v-if="logDirs.length"
                        v-for="({dirLabel, dirValue}, index) in logDirs"
                        :key="index"
                        :label="dirLabel"
                        :value="dirValue"
                        @change="selectLogDir" />
            </select>
        </div>
        <Loading v-if="gatheringContents" />
        <div v-if="!gatheringContents && (logFileContents.length || errFileContents.length)"
             id="accordion-logs">
            <div  v-if="logFileContents.length"
                  class="accordion-logs__log-accordion">
                <p class="h3 mb-3" v-text="logFileHeader"/>
                <div class="accordion" id="log-files">
                    <AccordionItem v-for="({id, fileDate, fileName, filePath}, index) in logFileContents"
                                   :key="index"
                                   :api-url="apiGetLogUrl"
                                   :title="dirSelect === 'api' ? fileDate : fileName"
                                   type="success"
                                   :accordion-id="id"
                                   :loading="gatheringFile"
                                   :file-path="filePath"
                                   :fileDir="dirSelect"
                                   @deleteFile="deleteFile(id, filePath)" />
                </div>
            </div>
            <div v-if="errFileContents.length"
                 class="accordion-logs__err-accordion">
                <p class="h3 mb-3" v-text="errFileHeader" />
                <div class="accordion" id="error-files">
                    <AccordionItem v-for="({id, fileDate, fileName, filePath}, index) in errFileContents"
                                   :key="index"
                                   :api-url="apiGetLogUrl"
                                   :title="dirSelect === 'api' ? fileDate : fileName"
                                   type="err"
                                   :accordion-id="id"
                                   :loading="gatheringFile"
                                   :file-path="filePath"
                                   :fileDir="dirSelect"
                                   @deleteFile="deleteFile(id, filePath)" />
                </div>
            </div>
        </div>
    </div>
</template>

<script type="application/javascript">
    /**
     * Node Packages
     */
    import axios from "axios";
    import "bootstrap/js/dist/collapse.js";
    
    /**
     * Components
     */
    import AccordionItem from "../blocks/AccordionItem.vue";
    import Loading from "../blocks/Loading.vue";
    import Button from "../blocks/Button.vue";
    
    export default {
        data() {
            return {
                api: MERCK_LOG.apiLog,
                apiDeleteFile: MERCK_LOG.apiDeleteFile,
                apiGetLogUrl: MERCK_LOG.apiGetLogUrl,
                apiLogDir: MERCK_LOG.apiGetLogDirs,
                buttonAtts: {
                    btnClass: "merck-scraper-log__refresh-logs",
                    btnId: "expand-refresh-btn",
                    title: "Refresh Logs",
                    type: "success",
                },
                dirSelect: "default",
                errFileContents: [],
                errFileHeader: "",
                gatheringContents: true,
                gatheringFile: false,
                logDirs: [],
                logFileContents: [],
                logFileHeader: "",
                selectDisabled: false,
            };
        },
        mounted() {
            this.getLogDirs();
            // this.getLogContents();
        },
        methods: {
            async getLogDirs() {
                this.fetchSetup();
                
                await axios
                    .get(this.apiLogDir)
                    .then(({data}) => this.logDirs = data)
                    .catch(err => console.error(err))
                    .finally(() => this.fetchComplete());
            },
            async selectLogDir(dirType) {
                this.fetchSetup();
    
                this.logFileHeader = `${dirType} Log Files`;
                this.errFileHeader = `${dirType} Error Files`
                
                await this.getLogContents(false, dirType)
                          .finally(() => this.fetchComplete());
            },
            async getLogContents(refresh = false, dirType = '') {
                this.fetchSetup();
                
                if (refresh) {
                    dirType = this.dirSelect;
                }
                
                await axios
                    .get(`${this.api}/${dirType}`)
                    .then(({data}) => data)
                    .then(({logsFiles, errFiles}) => {
                        this.logFileContents = logsFiles !== undefined && logsFiles;
                        this.errFileContents = errFiles !== undefined && errFiles;
                    })
                    .catch(err => console.error(err))
                    .finally(() => this.fetchComplete());
            },
            refreshContent() {
                this.getLogContents(true);
            },
            deleteFile(fileId, filePath) {
                this.$confirm(
                    "Are you sure you wish to delete this log file?",
                    `Delete ${fileId}?`,
                    "warning",
                    {
                        allowEnterKey: true,
                        allowEscapeKey: true,
                        allowOutsideClick: true,
                        confirmButtonText: "Delete",
                        confirmButtonAriaLabel: "Delete",
                        cancelButtonText: "Do not delete",
                        cancelButtonAriaLabel: "Do not delete",
                        confirmButtonColor: "#DC3545",
                    }
                    )
                    .then(() => {
                        axios
                            .post(
                                `${this.apiDeleteFile}/${fileId}`,
                                {filePath,}
                            )
                            .then(({data}) => {
                                const message = data.message;
                                let status = false;
                                let title = "File not found";
                                let type = "error";
                                let opts = {
                                    confirmButtonText: "Close",
                                    confirmButtonAriaLabel: "Close",
                                };
                            
                                if (data.status === 200) {
                                    status = true;
                                    title = "File Deleted";
                                    type = "success";
                                    opts = {
                                        timer: 2000,
                                    };
                                }
                            
                                this.$alert(
                                    message,
                                    title,
                                    type,
                                    opts
                                );
                            
                                return status;
                            })
                            .then((status) => status && this.refreshContent())
                            .catch(err => console.error(err.message));
                    })
                    .catch(err => console.error(err.message));
            },
            fetchSetup() {
                this.gatheringContents = true;
                this.selectDisabled = true;
                this.logFileContents = [];
                this.errFileContents = [];
            },
            fetchComplete() {
                this.gatheringContents = false;
                this.selectDisabled = false;
            }
        },
        watch: {
            dirSelect: function(dirType) {
                this.selectLogDir(dirType)
            }
        },
        components: {AccordionItem, Button, Loading},
        name: "ApiLog"
    };
</script>

<style scoped>
</style>
