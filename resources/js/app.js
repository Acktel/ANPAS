import './bootstrap.js';
import './vendor/demo.min.js';
import './vendor/demo-theme.min.js';
import './vendor/tabler.min.js';
import '@fortawesome/fontawesome-free/js/all.js';

import { enableRowDragDrop } from './modules/dragdrop.js'; 
import './modules/datatables.js';
import './pages/riepilogo-costi';

import $ from 'jquery';
window.$ = window.jQuery = $;