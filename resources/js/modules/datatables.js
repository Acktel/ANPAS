import $ from 'jquery';
import 'datatables.net-bs5';

$(document).ready(function () {
    if ($('#some-table').length) {
        $('#some-table').DataTable();
    }
});
