<template>
    <div class="merck-geolocation-container row my-2">
        <div class="col-12">
            <div v-if="error"
                 :class="`alert-danger ${msgContainerClasses}`" >
                <p :class="`link-danger ${msgClasses}`"
                   v-html="errorMsg" />
            </div>
            <div v-if="success"
                 :class="`alert-success ${msgContainerClasses}`">
                <p :class="`link-success ${msgClasses}`"
                   v-html="successMsg" />
            </div>
            <button type="button"
                    @click.prevent="updateGeoLocation"
                    class="btn btn-success"
                    v-html="getText" />
        </div>
    </div>
</template>

<script type="application/javascript">
    /**
     * JS Scripts
     */
    import axios from "axios";
    
    export default {
        props: {},
        data: () => ({
            apiUrl: MERCK_GEO.apiUrl,
            error: false,
            errorMsg: "Error retrieving the Lat/Lng for this location",
            getText: MERCK_GEO.getText,
            id: MERCK_GEO.id,
            msgContainerClasses: 'p-2 mb-3',
            msgClasses: 'm-0',
            success: false,
            successMsg: "Updated the Lat/Lng for this location"
        }),
        beforeCreate() {},
        created() {},
        beforeMount() {},
        mounted() {},
        methods: {
            updateGeoLocation() {
                this.success = false;
                this.error = false;
                axios
                    .post(`${this.apiUrl}/${this.id}`)
                    .then(({data, status}) => {
                        if (status === 200) {
                            const {latitude, longitude} = data;
                            if (latitude && longitude) {
                                const latitudeField = document.getElementById("ms_location_latitude");
                                const longitudeField = document.getElementById("ms_location_longitude");
                                this.success = true;
                                latitudeField.value = latitude;
                                longitudeField.value = longitude;
                            }
                        }
                    })
                    .then(() => {
                        setTimeout(() => {
                            this.success = false;
                        }, 5000);
                    })
                    .catch(err => {
                        this.error = true;
                        console.error(err.toString());
                    });
            }
        },
        computed: {},
        watch: {},
        components: {},
        name: "LocationGeoFetch"
    };
</script>

<style scoped>

</style>
