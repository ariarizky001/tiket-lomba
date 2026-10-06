const mix = require('laravel-mix');

mix.js('resources/js/client/admin/roots/index.js', 'public/js/admin.js')
   .react()
   .extract()
   .css('node_modules/antd/dist/antd.css', 'public/css/antd.css')
   .version()
   .options({
       legacyNodePolyfills: false
   });
