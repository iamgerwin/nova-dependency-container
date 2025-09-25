const mix = require('laravel-mix');
const path = require('path');

mix.alias({
  'laravel-nova': path.join(__dirname, 'vendor/laravel/nova/resources/js/mixins/packages.js')
});