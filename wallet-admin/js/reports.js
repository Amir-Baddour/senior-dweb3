(function(){
  // ✅ FIX: Use ADMIN_CONFIG from config.js
  const API_BASE = window.ADMIN_CONFIG?.API_BASE_URL || 
    "http://localhost/digital-wallet-plateform/wallet-server/admin/v1";
  
  console.log('[reports.js] Using API_BASE:', API_BASE);
  
  const token = localStorage.getItem("admin_jwt");
  if (!token) {
    console.error('[reports.js] No admin_jwt token found');
    return;
  }

  const reportType = document.getElementById("reportType");
  const reportFrom = document.getElementById("reportFrom");
  const reportTo   = document.getElementById("reportTo");
  const btnCsv     = document.getElementById("exportCsv");
  const btnPdf     = document.getElementById("exportPdf");

  function setDefaults() {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - 30);
    reportFrom.value = from.toISOString().slice(0,10);
    reportTo.value   = to.toISOString().slice(0,10);
  }

  async function download(format) {
    const params = new URLSearchParams({
      report: reportType.value,
      from: reportFrom.value || "",
      to: reportTo.value || "",
      format
    });

    const url = `${API_BASE}/report.php?${params.toString()}`;
    console.log('[reports.js] Downloading from:', url);

    try {
      const res = await fetch(url, {
        method: 'GET',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Accept': format === 'pdf' ? 'application/pdf' : 'text/csv'
        }
      });

      if (!res.ok) {
        const text = await res.text();
        console.error('[reports.js] Export failed:', text);
        alert("Export failed: " + text);
        return;
      }

      const blob = await res.blob();
      const downloadUrl = URL.createObjectURL(blob);
      const a = document.createElement("a");
      const ext = format === "pdf" ? "pdf" : "csv";
      a.href = downloadUrl;
      a.download = `${reportType.value}_${reportFrom.value}_${reportTo.value}.${ext}`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(downloadUrl);
      
      console.log('[reports.js] Download successful');
    } catch (error) {
      console.error('[reports.js] Download error:', error);
      alert("Export failed: " + error.message);
    }
  }

  btnCsv.addEventListener("click", () => {
    console.log('[reports.js] CSV export clicked');
    download("csv");
  });
  
  btnPdf.addEventListener("click", () => {
    console.log('[reports.js] PDF export clicked');
    download("pdf");
  });

  setDefaults();
  console.log('[reports.js] Initialized');
})();