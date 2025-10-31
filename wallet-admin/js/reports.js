(function(){
  const API_BASE = "http://localhost/digital-wallet-plateform/wallet-server/admin/v1";
  const token = localStorage.getItem("admin_jwt");
  if (!token) return;

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

    const res = await fetch(`${API_BASE}/report.php?${params.toString()}`, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (!res.ok) {
      const text = await res.text();
      alert("Export failed: " + text);
      return;
    }

    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    const ext = format === "pdf" ? "pdf" : "csv";
    a.href = url;
    a.download = `${reportType.value}_${reportFrom.value}_${reportTo.value}.${ext}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  btnCsv.addEventListener("click", () => download("csv"));
  btnPdf.addEventListener("click", () => download("pdf"));

  setDefaults();
})();
