(function () {
  const API_BASE = "http://localhost/digital-wallet-plateform/wallet-server/admin/v1";
  const token = localStorage.getItem("admin_jwt");
  if (!token) { window.location.href = "login.html"; return; }

  // Elements
  const elTotalUsers = document.getElementById("kpiTotalUsers");
  const elNewUsers7d = document.getElementById("kpiNewUsers7d");
  const elTxVolume   = document.getElementById("kpiTxVolume");
  const elTxCount    = document.getElementById("kpiTxCount");

  const fromDate = document.getElementById("fromDate");
  const toDate   = document.getElementById("toDate");
  const applyBtn = document.getElementById("applyFilters");
  const resetBtn = document.getElementById("resetFilters");

  let userGrowthChart, txVolumeChart, txTypeChart, topCoinsChart;

  function defaultRange() {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - 30);
    fromDate.value = from.toISOString().slice(0,10);
    toDate.value   = to.toISOString().slice(0,10);
  }

  async function fetchAnalytics() {
    const params = { from: fromDate.value || "", to: toDate.value || "" };
    const res = await axios.get(`${API_BASE}/analytics.php`, {
      params,
      headers: { Authorization: `Bearer ${token}` }
    });
    if (res.status !== 200) throw new Error("HTTP " + res.status);
    if (res.data && res.data.error) throw new Error(res.data.error);
    return res.data;
  }

  function fmtMoney(n) {
    if (n == null) return "—";
    const num = Number(n);
    return isNaN(num) ? "—" : num.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
  }

  function destroy(c) { if (c) c.destroy(); }

  function renderKpis(data) {
    elTotalUsers.textContent = data.total_users?.toLocaleString() ?? "—";
    elNewUsers7d.textContent = data.new_users_7d?.toLocaleString() ?? "—";
    elTxVolume.textContent   = fmtMoney(data.tx_volume_range);
    elTxCount.textContent    = (data.tx_count_range ?? "—");
  }

  // ultra-compact base options
  const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,    // height controlled by CSS wrapper
    layout: { padding: { top: 4, right: 6, bottom: 4, left: 6 } },
    plugins: {
      legend: { display: false },  // hide legends to save space
      tooltip: { intersect: false, mode: 'index' }
    },
    scales: {
      x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 10 } } },
      y: { grid: { display: false }, ticks: { font: { size: 10 } }, beginAtZero: true }
    },
    elements: { point: { radius: 1.5 }, line: { borderWidth: 2 } }
  };

  function renderCharts(d) {
    destroy(userGrowthChart);
    userGrowthChart = new Chart(document.getElementById("userGrowthChart"), {
      type: "line",
      data: {
        labels: d.user_growth.map(x => x.date),
        datasets: [{ label: "New Users", data: d.user_growth.map(x => x.count), fill: false, tension: 0.25 }]
      },
      options: baseOpts
    });

    destroy(txVolumeChart);
    txVolumeChart = new Chart(document.getElementById("txVolumeChart"), {
      type: "bar",
      data: {
        labels: d.tx_volume_daily.map(x => x.date),
        datasets: [{ label: "Daily Volume", data: d.tx_volume_daily.map(x => x.volume), borderWidth: 1 }]
      },
      options: baseOpts
    });

    destroy(txTypeChart);
    txTypeChart = new Chart(document.getElementById("txTypeChart"), {
      type: "doughnut",
      data: {
        labels: d.tx_by_type.map(x => x.type),
        datasets: [{ data: d.tx_by_type.map(x => x.count) }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }, // hide legend for compactness
        cutout: '60%'  // thinner ring
      }
    });

    destroy(topCoinsChart);
    topCoinsChart = new Chart(document.getElementById("topCoinsChart"), {
      type: "bar",
      data: {
        labels: d.top_coins.map(x => x.coin_symbol),
        datasets: [{ label: "Volume", data: d.top_coins.map(x => x.volume), borderWidth: 1 }]
      },
      options: { ...baseOpts, indexAxis: "y" }
    });
  }

  async function load() {
    try {
      const data = await fetchAnalytics();
      renderKpis(data);
      renderCharts(data);
    } catch (e) {
      const msg =
        e?.response?.data?.error ||
        (typeof e?.response?.data === 'string' ? e.response.data : '') ||
        e.message;
      alert('Analytics failed: ' + msg);
    }
  }

  applyBtn.addEventListener("click", load);
  resetBtn.addEventListener("click", () => { defaultRange(); load(); });

  defaultRange();
  load();
})();
