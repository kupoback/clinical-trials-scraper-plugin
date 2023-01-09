let mix = require('laravel-mix');

const adminDir = {
    dist: "admin/dist/",
    js: "admin/scripts",
    css: "admin/styles",
};
const frontendDir = {
    dist: "frontend/dist/",
    js: "frontend/scripts",
    css: "frontend/styles",
};

mix.js(`${adminDir.js}/merck-scraper-admin.js`, adminDir.dist);
mix.js(`${adminDir.js}/merck-scraper-disable-publish-sidebar.js`, adminDir.dist);
mix.js(`${adminDir.js}/merck-scraper-vue.js`, adminDir.dist)
    .vue();
mix.js(`${frontendDir.js}/merck-scraper-frontend.js`, frontendDir.dist)
   .vue();

mix.sass(`${adminDir.css}/merck-scraper-admin.scss`, adminDir.dist)
   .options({
        postCss: [
           require('postcss-custom-properties')
        ]
    });

mix.sass(`${frontendDir.css}/merck-scraper-frontend.scss`, frontendDir.dist)
   .options({
        postCss: [
           require('postcss-custom-properties')
        ]
    });

mix.browserSync({
    proxy: "https://merck.test",
    files: ["./**/*.php", "./dist/**/*.*"],
    open: false,
});
