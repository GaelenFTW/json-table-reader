<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Beautifier — Expandable Table</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        .badge.string { background: #e0f7fa; color: #006064; }
        .badge.number { background: #f1f8e9; color: #33691e; }
        .badge.object { background: #ede7f6; color: #4527a0; }
        .badge.array { background: #fff3e0; color: #e65100; }
    </style>
</head>
<body>
    <h2>JSON Beautifier — Expandable Table</h2>

    <textarea id="inputJson" rows="4" cols="80" placeholder="Paste JSON or URL here..."></textarea><br>
    <button id="fetchJson">Beautify</button>
    <button id="expandAll">Expand All</button>
    <button id="collapseAll">Collapse All</button>
    <button id="clearTable">Clear</button>

    <hr>

    <table id="jsonTable" class="display" style="width:100%">
        <thead><tr id="jsonHeader"></tr></thead>
        <tbody></tbody>
    </table>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        let table;

        function typeBadge(val) {
            if (Array.isArray(val)) return '<span class="badge array">Array(' + val.length + ')</span>';
            if (typeof val === 'object' && val !== null) return '<span class="badge object">Object</span>';
            if (typeof val === 'string') return '<span class="badge string">String</span>';
            if (typeof val === 'number') return '<span class="badge number">Number</span>';
            return val;
        }

        function buildNestedTable(obj) {
            if (typeof obj !== 'object' || obj === null) return obj;

            let html = '<table class="display compact" style="margin-left:20px">';
            html += '<thead><tr>';
            Object.keys(obj[0] || obj).forEach(k => html += '<th>' + k + '</th>');
            html += '</tr></thead><tbody>';

            let rows = Array.isArray(obj) ? obj : [obj];
            rows.forEach(row => {
                html += '<tr>';
                Object.keys(row).forEach(k => {
                    html += '<td>' + (typeof row[k] === 'object' ? typeBadge(row[k]) : row[k]) + '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        }

        function renderTable(data) {
            if (table) {
            // Close child rows before destroy
            $('#jsonTable tbody tr').each(function () {
                let row = table.row(this);
                if (row.child && row.child.isShown()) {
                    row.child.hide();
                }
            });

            table.clear().destroy();
            table = null;
            }

            $('#jsonHeader').empty();
            $('#jsonTable tbody').empty();

            if (!Array.isArray(data)) data = [data];

            let keys = Object.keys(data[0] || {});
            $('#jsonHeader').append('<th></th>');
            keys.forEach(k => $('#jsonHeader').append('<th>' + k + '</th>'));

            data.forEach(row => {
                let tr = '<tr><td class="details-control">+</td>';
                keys.forEach(k => {
                    let val = row[k];
                    tr += '<td>' + (typeof val === 'object' ? typeBadge(val) : val) + '</td>';
                });
                tr += '</tr>';
                $('#jsonTable tbody').append(tr);
            });

            table = $('#jsonTable').DataTable();

            // Row expansion (unbind first to prevent duplicate listeners)
            $('#jsonTable tbody').off('click', 'td.details-control').on('click', 'td.details-control', function () {
                let tr = $(this).closest('tr');
                let row = table.row(tr);

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                    $(this).text('+');
                } else {
                    let rowData = data[row.index()];
                    let nestedHtml = '<div>';
                    Object.entries(rowData).forEach(([k, v]) => {
                        if (typeof v === 'object' && v !== null) {
                            nestedHtml += '<b>' + k + '</b>: ' + buildNestedTable(v) + '<br>';
                        }
                    });
                    nestedHtml += '</div>';
                    row.child(nestedHtml).show();
                    tr.addClass('shown');
                    $(this).text('–');
                }
            });

            // Expand all
            $('#expandAll').off().on('click', () => {
                $('#jsonTable tbody tr').each(function () {
                    let row = table.row(this);
                    if (!row.child.isShown()) {
                        let rowData = data[row.index()];
                        let nestedHtml = '<div>';
                        Object.entries(rowData).forEach(([k, v]) => {
                            if (typeof v === 'object' && v !== null) {
                                nestedHtml += '<b>' + k + '</b>: ' + buildNestedTable(v) + '<br>';
                            }
                        });
                        nestedHtml += '</div>';
                        row.child(nestedHtml).show();
                        $(this).addClass('shown').find('td.details-control').text('–');
                    }
                });
            });

            // Collapse all
            $('#collapseAll').off().on('click', () => {
                $('#jsonTable tbody tr').each(function () {
                    let row = table.row(this);
                    if (row.child.isShown()) {
                        row.child.hide();
                        $(this).removeClass('shown').find('td.details-control').text('+');
                    }
                });
            });
        }

        // Fetch JSON
        $('#fetchJson').on('click', () => {
            let input = $('#inputJson').val().trim();

            $.ajax({
                url: "/fetch-json",
                method: "POST",
                data: { input: input, _token: $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) {
                        renderTable(res.data);
                    } else {
                        alert("Error: " + res.error);
                    }
                },
                error: function (xhr) {
                    alert("Failed: " + xhr.responseText);
                }
            });
        });
          // Clear button
          $('#clearTable').on('click', () => {
              $('#inputJson').val('');

              if (table) {
                  $('#jsonTable tbody').off('click', 'td.details-control'); // remove old listeners

                  // Close all expanded child rows
                  $('#jsonTable tbody tr').each(function () {
                      let row = table.row(this);
                      if (row.child && row.child.isShown()) {
                          row.child.hide();
                      }
                  });

                  table.clear().destroy();
                  table = null;
              }

              $('#jsonHeader').empty();
              $('#jsonTable tbody').empty();
          });
          
    </script>
</body>
</html>
