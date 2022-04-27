<template>
    <Layout container-classes="merck-scraper-import__container"
            row-classes="merck-scraper-import__row">
        <template v-slot:body>
            <div class="merck-scraper-import__body col-12 mb-3">
                <p class="h4"
                   v-text="`Click the Import All button below to execute the scrapper.`" />
            </div>
            <div v-if="importRunning"
                 class="merck-scraper-import__body__status col-12">
                <p class="h5"
                   v-text="`Import Status`" />
                <p v-if="importStatus.position > 0 && importStatus.totalCount > 0"
                   class="merck-scraper-import__body__status-position">
                    <span v-text="importStatus.position" />/<span v-text="importStatus.totalCount" />
                </p>
                <ImportProgress :title="importStatus.title"
                                progress-class="col-6"
                                :helper="importStatus.helper"
                                :position="importStatus.position"
                                :total-count="importStatus.totalCount" />
                <p v-if="importStatus.helper"
                   class="merck-scraper-import__body__status-helper"
                   v-html="importStatus.helper" />
            </div>
            <div class="merck-scraper-import__button-group col-12">
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <div class="form-field-container">
                            <label for="nctid-field" class="form-label" v-text="`NCT ID's`"/>
                            <textarea v-model="nctidField"
                                      class="form-control"
                                      id="nctid-field"
                                      rows="3" />
                            <p class="helper-text">Enter in one NCT ID per line, and separate them with a semi-colon.</p>
                        </div>
                    </div>
                </div>
                <hr>
                <p class="h5 mt-4 mb-3"
                   v-text="`Trigger Import`" />
                <p v-if="importRunning" class="helper-text" v-text="`Import running, please wait.`" />
                <Button v-if="!importRunning && !locImportRunning"
                        btn-class="merck-scraper-import__button-group__btn-all"
                        btn-id="scraper-import"
                        title="Import All Trials and Locations"
                        :disabled="importRunning"
                        type="primary"
                        :emit-event="executeImport" />
                <Button v-if="!locImportRunning && !importRunning"
                        btn-class="merck-scraper-import__button-group__btn-locations"
                        btn-id="scraper-import"
                        title="Import All Locations"
                        :disabled="locImportRunning"
                        type="info"
                        :emit-event="executeLocationsImport" />
                <Button v-if="importRunning || locImportRunning"
                        btn-class="merck-scraper-import__button-group__btn-clear"
                        btn-id="scraper-import-clear"
                        title="Clear Import Progress"
                        :disabled="!importRunning || !locImportRunning"
                        type="warning"
                        :emit-event="stopImport" />
            </div>
        </template>
    </Layout>
</template>

<script type="application/javascript">
    /**
     * NPM packages
     */
    import axios from "axios";
    
    /**
     * Vue Components
     */
    import Button from "../blocks/Button.vue";
    import ImportProgress from "../blocks/ImportProgress.vue";
    import Layout from "../Layout/Layout.vue";
    
    export default {
        data() {
            return {
                api: MERCK_API.apiUrl,
                apiClearPosition: MERCK_API.apiClearPosition,
                apiLocationsUrl: MERCK_API.apiLocationsUrl,
                apiPosition: MERCK_API.apiPosition,
                data: null,
                importRunning: false,
                importStatus: null,
                locImportRunning: false,
                nctidField: null,
                spinner: null,
                timeout: 1000,
            };
        },
        mounted() {
            this.getImportPosition();
            this.intervalFetchData(this.timeout);
        },
        methods: {
            async executeImport() {
                this.importRunning = true;
                let config = {manualCall: true};
                
                if (this.nctidField) {
                    config.nctidField = this.nctidField;
                }
                
                await axios
                    .post(`${this.api}`, config,)
                    .catch(err => console.error(err));
    
                this.timeout = 100;
            },
            async executeLocationsImport() {
                this.locImportRunning = true;
                await axios
                    .post(this.apiLocationsUrl)
                    .catch(err => console.errog(err))
                
                this.timeout = 100;
            },
            getImportPosition() {
                axios
                    .get(`${this.apiPosition}`)
                    .then(({data}) => {
                        this.timeout = data.status === 200 ? 200 : 10000;
                        this.importStatus = data.status === 200 && data;
                        this.importRunning = data.status === 200;
                        this.locImportRunning = data.status === 200 && data;
                    })
                    .catch(err => console.error(err.toJSON()));
            },
            stopImport() {
                axios
                    .get(this.apiClearPosition)
                    .then(({data}) => {
                        this.importStatus = data.status === 200 && data;
                        this.importRunning = data.status === 200;
                        this.locImportRunning = data.status === 200;
                        this.timeout = 10000;
                    })
                    .catch(err => console.error(err.toJSON()));
            },
            intervalFetchData(interval) {
                setInterval(() => {
                    this.getImportPosition();
                }, interval);
            }
        },
        components: {ImportProgress, Layout, Button},
        name: "ApiImport",
    };
</script>

<style scoped>

</style>
