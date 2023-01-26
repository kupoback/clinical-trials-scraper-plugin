<template>
    <div :class="`accordion-item multi-collapse ${type}`">
        <h2 class="accordion-header"
            :id="`${type}-${accordionId}-header`">
            <span class="delete-file"
                 @click.prevent="$emit('deleteFile', {accordionId})">
                <svg height="20px" viewBox="0 0 512 512" width="20px" xmlns="http://www.w3.org/2000/svg"><path
                    d="m256 0c-141.164062 0-256 114.835938-256 256s114.835938 256 256 256 256-114.835938 256-256-114.835938-256-256-256zm0 0" fill="#f44336"/><path d="m350.273438 320.105469c8.339843 8.34375 8.339843 21.824219 0 30.167969-4.160157 4.160156-9.621094 6.25-15.085938 6.25-5.460938 0-10.921875-2.089844-15.082031-6.25l-64.105469-64.109376-64.105469 64.109376c-4.160156 4.160156-9.621093 6.25-15.082031 6.25-5.464844 0-10.925781-2.089844-15.085938-6.25-8.339843-8.34375-8.339843-21.824219 0-30.167969l64.109376-64.105469-64.109376-64.105469c-8.339843-8.34375-8.339843-21.824219 0-30.167969 8.34375-8.339843 21.824219-8.339843 30.167969 0l64.105469 64.109376 64.105469-64.109376c8.34375-8.339843 21.824219-8.339843 30.167969 0 8.339843 8.34375 8.339843 21.824219 0 30.167969l-64.109376 64.105469zm0 0" fill="#fafafa"/></svg>
            </span>
            <button class="accordion-button collapsed"
                    type="button"
                    data-bs-toggle="collapse"
                    :data-bs-target="`#${type}-${accordionId}`"
                    aria-expanded="false"
                    :aria-controls="`${type}-${accordionId}`"
                    @click.prevent="getFile(accordionId, type, fileDir, filePath)"
                    @keypress.enter.prevent="getFile(accordionId, type, fileDir, filePath)"
                    v-text="title" />
        </h2>
        <div :id="`${type}-${accordionId}`"
             class="highlight accordion-collapse collapse"
             :aria-labelledby="`${type}-${accordionId}-header`"
             data-bs-parent="#accordion-logs">
            <div class="accordion-body">
                <Loading v-if="loadingFile" />
                <button v-if="!loadingFile && download"
                        class="btn btn-info btn-sm"
                        @click.prevent="downloadFile"
                        v-html="`Download File`" />
                <vue-code-highlight
                    v-if="!loadingFile && fileContents"
                    :language="fileType">
                    <pre v-html="fileContents" />
                </vue-code-highlight>
            </div>
        </div>
    </div>
</template>

<script type="application/javascript">
    /**
     * @link https://www.npmjs.com/package/vue-code-highlight
     * @link https://prismjs.com/#supported-languages
     */
    import axios from "axios";
    import {component as VueCodeHighlight} from "vue-code-highlight"; // Log Parser
    import "vue-code-highlight/themes/prism-okaidia.css"; // Stylesheet for log parser
    
    import Loading from "./Loading.vue";
    
    export default {
        components: {Loading, VueCodeHighlight},
        props: {
            accordionId: String,
            apiUrl: String,
            download: {
                type: Boolean,
                default: false,
            },
            fileDir: String,
            fileName: String,
            filePath: String,
            loading: Boolean,
            title: String,
            type: String,
        },
        data() {
            return {
                fileContents: null,
                fileError: false,
                fileType: null,
                isOpen: false,
                loadingFile: false,
            }
        },
        mounted() {
            this.fileType = this.filePath.substring(this.filePath.lastIndexOf('.') + 1);
        },
        methods: {
            /**
             * Fetch the file contents
             * @param {string} fileName The files name
             * @param {string} fileType The file type
             * @param {string} fileDir  The file directory
             * @param {string} filePath The file path
             * @returns {Promise<void>}
             */
            async getFile(fileName = '', fileType = '', fileDir = '', filePath = '') {
                if (!fileName || this.fileContents) return;
                
                this.loadingFile = true;
                this.fileError = false;
                const data = {
                    fileType,
                    fileDir,
                    filePath,
                };
                
                await axios
                .post(`${this.apiUrl}/${fileName}`, data)
                .then(({data, status}) => {
                    if (data.mesasge || status !== 200) this.fileError = true;
                    this.fileContents = data.fileContents
                })
                .catch(err => console.error(err.message))
                .finally(() => this.loadingFile = false);
            },
            /**
             * Allows the user to download the specific file
             */
            downloadFile() {
                const file = new File(
                    [this.fileContents],
                    `${this.accordionId}.txt`,
                    {
                    type: 'text/plain',
                });
                
                const link = document.createElement('a');
                const url = URL.createObjectURL(file);
    
                link.href = url
                link.download = file.name
                document.body.appendChild(link)
                link.click()
    
                document.body.removeChild(link)
                window.URL.revokeObjectURL(url)
            }
        },
        name: "AccordionItem"
    };
</script>

<style scoped>
</style>
