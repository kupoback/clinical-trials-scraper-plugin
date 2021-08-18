<template>
    <div :class="`progress ${progressClass}`">
        <div class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar"
             :style="`width: ${currentPosition}%`"
             :aria-valuenow="currentPosition"
             aria-valuemin="0"
             aria-valuemax="100">{{currentPosition}}%</div>
    </div>
</template>

<script type="application/javascript">
    export default {
        props: {
            active: Boolean,
            progressClass: String,
            title: String,
            helper: String,
            position: [Number, String],
            totalCount: [Number, String],
        },
        mounted() {
        },
        methods: {
          progressBar() {
          
          }
        },
        computed: {
            currentPosition: function() {
                return Math.round(((this.position / this.totalCount) * 100) || 0);
            }
        },
        name: "ImportProgress"
    };
</script>

<style lang="scss" scoped>
    .progress {
        position: relative;
    }
</style>
