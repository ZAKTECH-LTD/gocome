require('./bootstrap');

import 'alpinejs'
import ApexCharts from 'apexcharts'
window.$ = require('jquery');
window.select2 = require('select2');
window.Swal = require('sweetalert2');
import fontawesome from '@fortawesome/fontawesome-free/js/all.js';
import 'livewire-sortable'
import * as FilePond from 'filepond';
window.FilePond = FilePond;
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';

// 
import intlTelInput from 'intl-tel-input';
window.intlTelInput = intlTelInput;