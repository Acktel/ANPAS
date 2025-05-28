// resources/js/app.js

// 1) Carica bootstrap.js (che registra CSRF, axios, ecc.)
require('./bootstrap');

// 2) Carica Tabler e demo (UMD / IIFE già pronti)
require('./vendor/demo.min.js');
require('./vendor/demo-theme.min.js');
require('./vendor/tabler.min.js');

// 3) Carica Alpine.js come CommonJS
window.Alpine = require('alpinejs');
window.Alpine.start();
