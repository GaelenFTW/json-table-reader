<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>JSON Beautifier — Expandable Nested Tables</title>

  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

  <style>
    /* small styling for nested tables/details */
    .nested-table { font-size: 0.9rem; margin-top: 6px; border-collapse: collapse; width:100%; }
    .nested-table th, .nested-table td { padding: 6px; border: 1px solid #e5e7eb; }
    .detail-key { padding: 6px 0; }
    .details-row td { background: #fafafa; }
    .view-field { cursor: pointer; }
    .highlight-flash { animation: highlight 1s ease; }
    @keyframes highlight {
      from { background: #fff59d; } to { background: transparent; }
    }
  </style>
</head>
<body class="bg-gray-50 p-6 text-gray-800">
  <div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold mb-4">JSON Beautifier — Expandable Nested Tables</h1>

    <label class="block mb-2 font-medium">Input JSON or URL</label>
    <textarea id="inputJson" rows="6" class="w-full p-3 border rounded" placeholder='Paste JSON or URL like https://api.example.com/data'></textarea>

    <div class="flex gap-2 mt-3">
      <button id="clientBeautify" class="px-4 py-2 bg-blue-600 text-white rounded">Render Table</button>
      <button id="clear" class="px-4 py-2 bg-red-200 rounded">Clear</button>
    </div>

    <div class="mt-6">
      <label class="block mb-2 font-medium">Result (Click + to expand)</label>
      <div id="tableContainer" class="overflow-x-auto bg-white shadow rounded p-4"></div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  <script>
    // helper: escape HTML to avoid XSS
    function escapeHtml(str) {
      if (str === null || str === undefined) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function isPrimitive(v) {
      return v === null || (typeof v !== 'object' && typeof v !== 'function');
    }

    // Render any value recursively: primitives, arrays, objects
    function formatValue(value) {
      if (value === null) return '<em>null</em>';
      if (typeof value === 'string') return escapeHtml(value);
      if (typeof value === 'number' || typeof value === 'boolean') return escapeHtml(String(value));

      if (Array.isArray(value)) {
        if (value.length === 0) return '<em>Empty array</em>';

        // array of objects -> table
        if (value.every(item => item && typeof item === 'object' && !Array.isArray(item))) {
          const headers = [...new Set(value.flatMap(item => Object.keys(item)))];
          let html = `<table class="nested-table">`;
          html += `<thead><tr>${headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead>`;
          html += `<tbody>`;
          value.forEach(row => {
            html += '<tr>';
            headers.forEach(h => {
              const cell = row[h];
              // allow recursion inside cells
              html += `<td>${formatValue(cell)}</td>`;
            });
            html += '</tr>';
          });
          html += `</tbody></table>`;
          return html;
        }

        // mixed array or array of primitives -> list
        return `<div><ul>${value.map(v => `<li>${formatValue(v)}</li>`).join('')}</ul></div>`;
      }

      // object -> list of key: value (values can be recursively formatted)
      if (typeof value === 'object') {
        let html = `<div>`;
        for (const [k, v] of Object.entries(value)) {
          // wrap each key/value so we can focus it later with data-key
          const safeKey = escapeHtml(k);
          html += `<div class="detail-key" data-key="${escapeHtml(k)}"><strong>${safeKey}</strong>: ${formatValue(v)}</div>`;
        }
        html += `</div>`;
        return html;
      }

      // fallback
      return escapeHtml(String(value));
    }

    // Build the details area for a row (shows each top-level key and formatted value)
    function formatRowDetails(rowObj) {
      let html = `<div class="row-details">`;
      for (const [k, v] of Object.entries(rowObj)) {
        html += `<div class="mb-3"><div class="font-semibold mb-1">${escapeHtml(k)}</div>${formatValue(v)}</div>`;
      }
      html += `</div>`;
      return html;
    }

    let dataTableInstance = null;

    function renderTableFromJson(jsonText) {
      let parsed;
      try {
        parsed = JSON.parse(jsonText);
      } catch (e) {
        alert("Invalid JSON: " + e.message);
        return;
      }

      if (!Array.isArray(parsed)) parsed = [parsed];

      if (parsed.length === 0) {
        document.getElementById("tableContainer").innerHTML = "<p>No data</p>";
        return;
      }

      // Destroy previous DataTable if exists
      if ($.fn.dataTable.isDataTable('#jsonTable')) {
        $('#jsonTable').DataTable().destroy();
      }

      // Collect headers
      const headers = [...new Set(parsed.flatMap(obj => Object.keys(obj)))];

      // Build main table HTML - first col is expand control
      let tableHtml = `<table id="jsonTable" class="display stripe w-full"><thead><tr><th></th>${headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead><tbody>`;

      parsed.forEach((row, idx) => {
        tableHtml += `<tr data-row-index="${idx}">`;
        tableHtml += `<td class="details-control text-center cursor-pointer" style="width:40px;">+</td>`;
        headers.forEach(h => {
          const val = row[h];
          if (val !== undefined && val !== null && typeof val === 'object') {
            // show a view button with preview
            const preview = Array.isArray(val) ? `Array(${val.length})` : 'Expand';
            tableHtml += `<td><button class="view-field px-2 py-1 rounded border text-sm" data-row="${idx}" data-key="${escapeHtml(h)}">${escapeHtml(preview)}</button></td>`;
          } else {
            tableHtml += `<td>${escapeHtml(val)}</td>`;
          }
        });
        tableHtml += `</tr>`;
      });

      tableHtml += `</tbody></table>`;

      document.getElementById("tableContainer").innerHTML = tableHtml;

      // Initialize DataTable
      dataTableInstance = $('#jsonTable').DataTable({
        pageLength: 10,
        autoWidth: false,
        columnDefs: [
          { orderable: false, targets: 0 } // disable sorting on expand column
        ]
      });

      // Expand/collapse row details (full row)
      $('#jsonTable tbody').off('click', 'td.details-control').on('click', 'td.details-control', function () {
        const tr = $(this).closest('tr');
        const rowIndex = parseInt(tr.attr('data-row-index'), 10);
        // find immediate next details-row; toggle if exists
        const next = tr.next('.details-row');
        if (next.length) {
          next.remove();
          $(this).text('+');
        } else {
          const rowData = parsed[rowIndex];
          const nestedHtml = formatRowDetails(rowData);
          tr.after(`<tr class="details-row"><td colspan="${headers.length + 1}">${nestedHtml}</td></tr>`);
          $(this).text('−');
        }
      });

      // Click view-field: expand (if needed) and scroll to the specific key inside details
      $('#jsonTable tbody').off('click', 'button.view-field').on('click', 'button.view-field', function (e) {
        e.stopPropagation();
        const rowIndex = parseInt($(this).attr('data-row'), 10);
        const key = $(this).attr('data-key');
        const tr = $(`#jsonTable tbody tr[data-row-index="${rowIndex}"]`);
        const control = tr.find('td.details-control');
        // if not expanded, click control to expand
        if (!tr.next().hasClass('details-row')) {
          control.trigger('click');
        }
        // after expand, find the detail-key with data-key and highlight/scroll
        setTimeout(() => {
          const detailsRow = tr.next('.details-row');
          if (!detailsRow.length) return;
          // find element inside details area that matches the key (use attribute selector)
          const target = detailsRow.find(`.detail-key[data-key="${key}"]`).first();
          if (target.length) {
            // temporarily highlight
            target.addClass('highlight-flash');
            // scroll into view inside the details row container
            target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            // remove highlight after a second
            setTimeout(() => target.removeClass('highlight-flash'), 1200);
          }
        }, 100);
      });
    }

    // detect URL vs raw JSON, support fetching
    async function processInput(input) {
      input = input.trim();
      if (!input) {
        alert('Please provide JSON or a URL');
        return;
      }
      if (/^https?:\/\//i.test(input)) {
        try {
          const res = await fetch(input);
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const data = await res.json();
          renderTableFromJson(JSON.stringify(data));
        } catch (e) {
          alert('Fetch failed: ' + e.message + '. If this is a cross-origin API, consider server-side fetch.');
        }
      } else {
        renderTableFromJson(input);
      }
    }

    document.getElementById('clientBeautify').addEventListener('click', () => {
      processInput(document.getElementById('inputJson').value);
    });
    document.getElementById('clear').addEventListener('click', () => {
      document.getElementById('inputJson').value = '';
      document.getElementById('tableContainer').innerHTML = '';
    });
  </script>
</body>
</html>
