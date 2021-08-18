<template>
    <button :class="`btn btn-${type} ${btnClass}`"
            :id="btnId"
            v-html="title"
            @click.prevent="emitEvent" />
</template>

<script type="application/javascript">

    export default {
        props: {
            btnClass: String,
            btnId: String,
            emitEvent: {
                Type: Function,
                default: () => {},
            },
            title: String,
            type: {
                Type: String,
                default: 'primary'
            }
        },
        name: "Button"
    };
</script>

<style scoped>

</style>
