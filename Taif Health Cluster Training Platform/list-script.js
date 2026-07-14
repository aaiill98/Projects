// تفعيل البحث على كل select يحمل الصنف 'searchable'
$(document).ready(function() {
  if ($.fn.select2) {
    $('.searchable').select2({
      width: '100%',
      placeholder: 'اختر من القائمة',
      allowClear: true,
      dir: 'rtl'
    });
  }

  // زر الطباعة
  $('#btnPrint, #btnPrint2').on('click', function(e){
    e.preventDefault();
    window.print();
  });

  // تصدير الجدول إلى Excel (إن وُجدت مكتبة XLSX نستخدمها، وإلا CSV احتياطي)
  function tableToWorkbook(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return null;
    var wb = XLSX.utils.book_new();
    var ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, 'Accepted');
    return wb;
  }

  function downloadCSV(tableId, filename) {
    const rows = Array.from(document.querySelectorAll('#' + tableId + ' tr')).map(tr =>
      Array.from(tr.querySelectorAll('th,td')).map(td => '"' + (td.innerText || '').replace(/"/g,'""') + '"').join(','));
    const blob = new Blob([rows.join('\n')], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename || 'table.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function handleExport() {
    const tableId = 'acceptedTable';
    if (window.XLSX) {
      const wb = tableToWorkbook(tableId);
      if (wb) { XLSX.writeFile(wb, 'accepted.xlsx'); return; }
    }
    // احتياطي CSV
    downloadCSV(tableId, 'accepted.csv');
  }

  $('#btnExport, #btnExport2').on('click', function(e){
    e.preventDefault();
    handleExport();
  });

  // تحديد الكل
  $('#selectAll').on('change', function(){
    const checked = this.checked;
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
  });
});


// ===== Patch: support #printBtn/#exportBtn with data-table fallback =====
(function(){
  function _resolveTableId(triggerEl){
    if (triggerEl){
      const attr = triggerEl.getAttribute && triggerEl.getAttribute('data-table');
      if (attr && attr.startsWith('#')) return attr.slice(1);
    }
    // try common ids
    const el = document.querySelector('table[id]');
    return el ? el.id : 'acceptedTable';
  }

  function _exportFrom(el){
    const tableId = _resolveTableId(el);
    if (window.XLSX && typeof tableToWorkbook === 'function'){
      const wb = tableToWorkbook(tableId);
      if (wb){ XLSX.writeFile(wb, 'export.xlsx'); return; }
    }
    if (typeof downloadCSV === 'function'){
      downloadCSV(tableId, 'export.csv');
    }
  }

  // bind if present
  const exp = document.getElementById('exportBtn');
  if (exp){ exp.addEventListener('click', function(e){ e.preventDefault(); _exportFrom(exp); }); }

  const prn = document.getElementById('printBtn');
  if (prn){ prn.addEventListener('click', function(e){ e.preventDefault(); window.print(); }); }
})();
// ===== End Patch =====
