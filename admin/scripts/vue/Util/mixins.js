export default {
    methods: {
        isActive(postId, id) {
            return postId === id ? "active" : "";
        },
        objNotEmpty(obj) {
            return Object.keys(obj).length !== 0;
        },
        objHasKey(obj, key) {
            return obj.hasOwnProperty(key);
        },
        camelToKebab(str) {
            return str.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
        }
    }
};
