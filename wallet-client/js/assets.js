// wallet-client/js/assets.js
// v3.2 — robust portfolio render with snapshot (no flicker), BTC-safe, tiny balances safe

document.addEventListener("DOMContentLoaded", () => {
  // ---------- DOM ----------
  const tbody     = document.querySelector(".asset-table tbody");
  const balanceEl = document.querySelector(".balance-large");
  const approxEl  = document.getElementById("fiatApprox"); // "≈ $..."
  const pnlEl     = document.getElementById("pnlLine");    // "PnL (last 1m) ..."

  // ---------- Config ----------
  const ORIGIN   = location.origin;
  const ROOT     = "/digital-wallet-plateform"; // adjust if deployed elsewhere
  const PHP_BASE = `${ORIGIN}${ROOT}/wallet-server/user/v1`;

  // PnL horizon: 1 minute
  const PNL_TTL_MS = 60 * 1000;
  const PNL_BASE_K = "assets.pnl.baseTotal";
  const PNL_TS_K   = "assets.pnl.baseTs";

  // Snapshot to avoid the “empty then fill” flicker when revisiting
  const SNAP_K = "assets.snapshot.v2";

  // Common symbol -> CoinGecko id fallbacks (incl. BTC synonyms)
  const FALLBACK_ID = {
    USDT:"tether", USDC:"usd-coin", DAI:"dai",
    BTC:"bitcoin", XBT:"bitcoin", WBTC:"wrapped-bitcoin",
    ETH:"ethereum", BNB:"binancecoin", XRP:"ripple",
    SOL:"solana", ADA:"cardano", DOGE:"dogecoin",
    MATIC:"polygon", AVAX:"avalanche-2", LTC:"litecoin",
    BCH:"bitcoin-cash", LINK:"chainlink", XLM:"stellar",
    TRX:"tron", TON:"the-open-network"
  };

  // ---------- Utils ----------
  const getToken = () =>
    localStorage.getItem("jwt") ||
    sessionStorage.getItem("jwt") ||
    localStorage.getItem("jwt_token") ||
    sessionStorage.getItem("jwt_token") ||
    "";

  function fmtAmount(n) {
    const x = Number(n);
    if (!isFinite(x) || x === 0) return "0";
    if (Math.abs(x) >= 1) return x.toLocaleString(undefined, { maximumFractionDigits: 4 });
    // for tiny crypto amounts (BTC, etc.), show up to 8 dp but trim trailing zeros
    return x.toFixed(8).replace(/0+$/,"").replace(/\.$/,"");
  }

  const fmtUSD = (n) => `$${(isFinite(n) ? n : 0).toFixed(2)}`;

  function iconCell(img, symbol, name) {
    const i = img ? `<img src="${img}" alt="${symbol}" width="18" height="18"
                       style="vertical-align:middle;border-radius:50%;margin-right:8px">` : "";
    return `${i}<strong>${symbol}</strong><br><span class="sub" style="font-size:12px;color:#777">${name || symbol}</span>`;
  }

  // ---------- Snapshot ----------
  function tryRenderSnapshot() {
    try {
      const snap = JSON.parse(sessionStorage.getItem(SNAP_K) || "null");
      if (!snap || !Array.isArray(snap.rows)) return;
      renderHeader(snap.totalUSDT);
      renderRows(snap.rows);
      renderPnl(snap.totalUSDT);
    } catch {}
  }

  function saveSnapshot(totalUSDT, rows) {
    try { sessionStorage.setItem(SNAP_K, JSON.stringify({ totalUSDT, rows, ts: Date.now() })); } catch {}
  }

  // ---------- API ----------
  async function fetchWallets() {
    const token = getToken();
    if (!token) throw new Error("No token");

    const r = await axios.get(`${PHP_BASE}/get_wallets.php`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (!r.data?.success) throw new Error(r.data?.error || "get_wallets failed");

    // Normalize and merge duplicates by symbol (case variations, etc.)
    const map = new Map();
    (r.data.wallets || []).forEach(w => {
      const sym = String(w.coin_symbol || "").toUpperCase().trim();
      const bal = Number(w.balance || 0);
      const lok = Number(w.locked_balance || 0);
      if (!map.has(sym)) map.set(sym, { symbol: sym, balance: 0, locked: 0 });
      const row = map.get(sym);
      row.balance += bal;
      row.locked  += lok;
    });

    return [...map.values()];
  }

  async function fetchCoinsCatalog() {
    const pages = [1,2,3,4,5];
    const all = [];
    for (const p of pages) {
      try {
        const r = await axios.get(`${PHP_BASE}/coins_proxy.php?page=${p}`);
        if (Array.isArray(r.data)) all.push(...r.data);
      } catch { /* tolerate partial pages */ }
    }

    const bySymbol = {};
    const byId = {};
    all.forEach(c => {
      if (!c || !c.id) return;
      const sym = String(c.symbol || "").toUpperCase();
      const meta = { id: c.id, symbol: sym, name: c.name || sym, image: c.image || "" };
      byId[c.id] = meta;
      if (sym && !bySymbol[sym]) bySymbol[sym] = meta;
    });
    return { bySymbol, byId };
  }

  async function fetchPriceById(id) {
    // PHP proxy returns { price_in_usdt }
    const r = await axios.get(`${PHP_BASE}/price_proxy.php?coin=${encodeURIComponent(id)}`);
    const p = Number(r.data?.price_in_usdt);
    if (!isFinite(p) || p <= 0) throw new Error(`Bad price for ${id}`);
    return p;
  }

  // ---------- Portfolio ----------
  async function buildPortfolio(wallets, catalog) {
    // Resolve coin metas by symbol (with fallbacks/synonyms)
    const wanted = new Map(); // symbol -> meta
    wallets.forEach(w => {
      const sym = w.symbol;
      let meta = catalog.bySymbol[sym];
      if (!meta) {
        const fid = FALLBACK_ID[sym] || sym.toLowerCase();
        meta = catalog.byId[fid] || { id: fid, symbol: sym, name: sym, image: "" };
      }
      wanted.set(sym, meta);
    });

    // Prices (limited concurrency); anchor stables at 1
    const ids = [...new Set([...wanted.values()].map(m => m.id))];
    const prices = { tether: 1, "usd-coin": 1, dai: 1 };
    const queue = ids.slice();
    const CONC = 5;

    async function worker() {
      while (queue.length) {
        const id = queue.shift();
        if (prices[id] !== undefined) continue;
        try { prices[id] = await fetchPriceById(id); }
        catch { prices[id] = 0; }
      }
    }
    await Promise.all(Array.from({ length: CONC }, worker));

    // Build rows and total
    let totalUSDT = 0;
    const rows = wallets.map(w => {
      const meta  = wanted.get(w.symbol);
      const price = (w.symbol === "USDT") ? 1 : Number(prices[meta.id] || 0);
      const usd   = Number(w.balance) * price;
      const free  = Math.max(0, Number(w.balance) - Number(w.locked || 0));

      totalUSDT += usd;

      return {
        symbol: meta.symbol,
        name: meta.name,
        image: meta.image,
        balance: Number(w.balance || 0),
        available: free,
        usd
      };
    });

    // Sort by USD desc
    rows.sort((a, b) => b.usd - a.usd);
    return { rows, totalUSDT };
  }

  // ---------- Render ----------
  function renderHeader(totalUSDT) {
    if (balanceEl) {
      // reset then append currency span
      balanceEl.textContent = `${fmtAmount(totalUSDT)} `;
      const span = document.createElement("span");
      span.className = "currency";
      span.textContent = "USDT";
      balanceEl.appendChild(span);
    }
    if (approxEl) approxEl.textContent = `≈ ${fmtUSD(totalUSDT)}`; // USDT ≈ USD
  }

  function renderRows(rows) {
    if (!tbody) return;
    tbody.innerHTML = "";

    rows.forEach(r => {
      // Show all coins; if you want to hide completely empty ones, uncomment next line:
      // if (r.balance <= 0 && r.usd <= 0) return;

      const tr = document.createElement("tr");

      const tdCoin = document.createElement("td");
      tdCoin.innerHTML = iconCell(r.image, r.symbol, r.name);

      const tdAmt = document.createElement("td");
      tdAmt.innerHTML = `${fmtAmount(r.balance)}<br><span style="font-size:12px;color:#777">${fmtUSD(r.usd)}</span>`;

      const tdAvail = document.createElement("td");
      tdAvail.textContent = fmtAmount(r.available);

      const tdAct = document.createElement("td");
      const a = document.createElement("a");
      a.href = "exchange.html";  // could be exchange.html?from=SYMBOL
      a.textContent = "Convert";
      tdAct.appendChild(a);

      tr.appendChild(tdCoin);
      tr.appendChild(tdAmt);
      tr.appendChild(tdAvail);
      tr.appendChild(tdAct);
      tbody.appendChild(tr);
    });
  }

  function renderPnl(currentTotalUSDT) {
    if (!pnlEl) return;

    const base = Number(localStorage.getItem(PNL_BASE_K));
    const ts   = Number(localStorage.getItem(PNL_TS_K));
    const now  = Date.now();

    if (!isFinite(base) || !ts || (now - ts) > PNL_TTL_MS) {
      localStorage.setItem(PNL_BASE_K, String(currentTotalUSDT));
      localStorage.setItem(PNL_TS_K, String(now));
      pnlEl.textContent = "PnL (last 1m) —";
      pnlEl.className = "pnl-neutral";
      return;
    }

    const diff = currentTotalUSDT - base;
    const pct  = base > 0 ? (diff / base) * 100 : 0;
    const sign = diff >= 0 ? "+" : "−";
    const absD = Math.abs(diff).toFixed(2);
    const absP = Math.abs(pct).toFixed(2);

    pnlEl.textContent = `PnL (last 1m) ${sign}$${absD} (${absP}%)`;
    pnlEl.className = diff >= 0 ? "pnl-gain" : "pnl-loss";
  }

  // ---------- Init ----------
  (async function init() {
    try {
      tryRenderSnapshot(); // instant paint

      const wallets = await fetchWallets();
      const catalog = await fetchCoinsCatalog();
      const { rows, totalUSDT } = await buildPortfolio(wallets, catalog);

      renderHeader(totalUSDT);
      renderRows(rows);
      renderPnl(totalUSDT);
      saveSnapshot(totalUSDT, rows);
    } catch (e) {
      console.error("[assets] init error:", e);
      if (e?.response?.status === 401) {
        alert("Session expired. Please log in again.");
        window.location.href = "login.html";
        return;
      }
      alert("Failed to load assets. Please refresh.");
    }
  })();
});
