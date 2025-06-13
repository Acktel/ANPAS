const mix = require('laravel-mix');

mix
  // 1) Bundle JS: entry point unico in cui, in app.js, fai
  //    import './bootstrap.js';
  //    import './vendor/demo.min.js';
  //    import './vendor/demo-theme.min.js';
  //    import './vendor/tabler.min.js';
  .js('resources/js/app.js', 'public/js')

  // 2) Bundle CSS: unisci demo, app.css e tabler-vendors ecc.
  .styles([
    'resources/css/tabler-vendors.min.css',
    'resources/css/tabler-flags.min.css',
    'resources/css/tabler-payments.min.css',
    'resources/css/tabler-marketing.min.css',
    'resources/css/tabler-socials.min.css',
    'resources/css/demo.min.css',
    'resources/css/app.css',
  ], 'public/css/app.css')

  // 3) Copia separatamente Tabler “vanilla” se ti serve ancora
  .copy('resources/css/tabler.min.css', 'public/css/tabler.min.css')

  // 4) Copia fonts e immagini
  .copyDirectory('resources/fonts', 'public/fonts')
  .copyDirectory('resources/images', 'public/images')

  // 5) Versioning per cache-busting
  .version();
