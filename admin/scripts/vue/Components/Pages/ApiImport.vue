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
                <p v-if="status.position > 0 && status.totalCount > 0"
                   class="merck-scraper-import__body__status-position">
                    <span v-text="status.position" />/<span v-text="status.totalCount" />
                </p>
                <ImportProgress :title="status.title"
                                progress-class="col-6"
                                :helper="status.helper"
                                :position="status.position"
                                :total-count="status.totalCount" />
                <p v-if="status.helper"
                   class="merck-scraper-import__body__status-helper"
                   v-html="status.helper" />
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
                            <p class="helper-text">Enter in one NCT ID per line, or separate them with a semi-colon.</p>
                        </div>
                    </div>
                </div>
                <hr>
                <p class="h5 mt-4 mb-3"
                   v-text="`Trigger Import`" />
                <p v-if="importRunning" class="helper-text" v-text="`Import running, please wait.`" />
                <Button v-if="!importRunning"
                        btn-class="merck-scraper-import__button-group__btn-all"
                        btn-id="scraper-import"
                        title="Import All"
                        :disabled="importRunning"
                        type="primary"
                        :emit-event="executeImport" />
                <Button v-if="importRunning"
                        btn-class="merck-scraper-import__button-group__btn-clear"
                        btn-id="scraper-import-clear"
                        title="Clear Import Progress"
                        :disabled="!importRunning"
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
                apiPosition: MERCK_API.apiPosition,
                data: null,
                importRunning: false,
                nctidField: null,
                spinner: null,
                status: null,
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
                let config = {};
                
                if (this.nctidField) {
                    config.nctidField = this.nctidField;
                }
                
                await axios
                    .post(`${this.api}`, config,)
                    .catch(err => console.error(err));
    
                this.timeout = 100;
            },
            getImportPosition() {
                axios
                    .get(`${this.apiPosition}`)
                    .then(({data}) => {
                        this.timeout = data.status === 200 ? 200 : 10000;
                        this.status = data.status === 200 && data;
                        this.importRunning = data.status === 200;
                    })
                    .catch(err => console.error(err.toJSON()));
            },
            stopImport() {
                axios
                    .get(this.apiClearPosition)
                    .then(({data}) => {
                        this.status = data.status === 200 && data;
                        this.importRunning = data.status === 200;
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
