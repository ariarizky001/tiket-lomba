const mix = require('laravel-mix');
const path = require('path');

mix.js('resources/js/client/admin/roots/index.js', 'public/js/admin.js')
   .react()
   .extract()
   .css('node_modules/antd/dist/antd.css', 'public/css/antd.css')
   .postCss('resources/css/app.css', 'public/css/app.css')
   .version()
   .options({
       legacyNodePolyfills: false,
       postCss: []
   })
   .webpackConfig({
       resolve: {
           alias: {
               '@': path.resolve(__dirname, 'resources/js/client'),
               moment: 'moment',
               dayjs: 'dayjs'
           },
           fallback: {
               "path": false,
               "fs": false
           }
       }
   });
