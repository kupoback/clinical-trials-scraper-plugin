<template>
    <div :class="`container-fluid p-0 ${containerClasses}`">
        <div :class="`row ${rowClasses}`">
            <slot name="body"></slot>
        </div>
    </div>
</template>

<script type="application/javascript">
    export default {
        props: {
            containerClasses: String,
            rowClasses: String,
            withSidebar: Boolean,
        },
        name: "Layout"
    };
</script>

<style scoped>

</style>
