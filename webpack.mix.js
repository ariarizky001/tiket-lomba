const mix = require('laravel-mix');

mix.js('resources/js/client/admin/roots/index.js', 'public/js/admin.js')
   .react()
   .extract()
   .css('node_modules/antd/dist/antd.css', 'public/css/antd.css')
   .postCss('resources/css/app.css', 'public/css/app.css')
   .version()
   .options({
       legacyNodePolyfills: false,
       postCss: []
   });
