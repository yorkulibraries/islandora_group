var table1 = $('#group-node-table').DataTable({
    "order": [[1, "desc"]],
    "iDisplayLength": 20,
    "bLengthChange": false,
    "bInfo" : false,
    "dom": "lifrtp"
});
$("select[name='group-node-table_length']").removeClass("input-sm");
$("select[name='group-node-table_length']").addClass("input-md");

var table2 = $('#group-children-nodes-has-access-control-table').DataTable({
    "order": [[1, "desc"]],
    "iDisplayLength": 20,
    "bLengthChange": false,
    "bInfo" : false,
    "dom": "lifrtp"
});
$("select[name='group-children-nodes-has-access-control-table-table_length']").removeClass("input-sm");
$("select[name='group-children-nodes-has-access-control-table-table_length']").addClass("input-md");


var table3 = $('#group-children-nodes-has-no-access-control-table').DataTable({
    "order": [[1, "desc"]],
    "iDisplayLength": 20,
    "bLengthChange": false,
    "bInfo" : false,
    "dom": "lifrtp"
});
$("select[name='group-children-nodes-has-no-access-control-table-table_length']").removeClass("input-sm");
$("select[name='group-children-nodes-has-no-access-control-table-table_length']").addClass("input-md");


var table4 = $('#group-media-has-no-access-control-table').DataTable({
    "order": [[1, "desc"]],
    "iDisplayLength": 20,
    "bLengthChange": false,
    "bInfo" : false,
    "dom": "lifrtp"
});
$("select[name='group-media-has-no-access-control-table-table_length']").removeClass("input-sm");
$("select[name='group-media-has-no-access-control-table-table_length']").addClass("input-md");


var table5 = $('#group-media-has-access-control-table').DataTable({
    "order": [[1, "desc"]],
    "iDisplayLength": 20,
    "bLengthChange": false,
    "bInfo" : false,
    "dom": "lifrtp"
});
$("select[name='group-media-has-access-control-table-table_length']").removeClass("input-sm");
$("select[name='group-media-has-access-control-table-table_length']").addClass("input-md");
