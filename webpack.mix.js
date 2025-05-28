const mix = require('laravel-mix');

mix
  // 1) Unisci tutti i CSS
  .styles([
    'resources/css/tabler.min.css',
    'resources/css/tabler-vendors.min.css',
    'resources/css/tabler-flags.min.css',
    'resources/css/tabler-payments.min.css',
    'resources/css/tabler-marketing.min.css',
    'resources/css/tabler-socials.min.css',
    'resources/css/demo.min.css',
    'resources/css/app.css',
  ], 'public/css/app.css')

  // 2) Unisci tutti i JS (vendor + il tuo app.js rivisto sopra)
  .scripts([
    'resources/js/vendor/demo.min.js',
    'resources/js/vendor/demo-theme.min.js',
    'resources/js/vendor/tabler.min.js',
    'resources/js/app.js',
  ], 'public/js/app.js')

  // 3) Copia fonts e immagini
  .copyDirectory('resources/fonts', 'public/fonts')
  .copyDirectory('resources/images', 'public/images')

  // 4) Versioning in produzione
  .version();
