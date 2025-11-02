// js/exchange.js — any2any v4.4 (hoist-safe, PHP proxies, processor endpoint)
document.addEventListener("DOMContentLoaded", () => {
  const { API_BASE_URL } = window.APP_CONFIG; // use config.js setting

  // ---------- Elements ----------
  const fromCoinInput = document.getElementById("fromCoin");
  const toCoinInput   = document.getElementById("toCoin");
  const fromCoinList  = document.getElementById("fromCoinList");
  const toCoinList    = document.getElementById("toCoinList");
  const fromAmount    = document.getElementById("fromAmount");
  const toAmount      = document.getElementById("toAmount");
  const rateEl        = document.getElementById("conversionRate");
  const convertBtn    = document.getElementById("convertBtn");
  const fromBalEl     = document.getElementById("fromBalance");
  const toBalEl       = document.getElementById("toBalance");

  // Make dropdowns overlay correctly without HTML changes
  document.querySelectorAll(".convert-box").forEach(box => {
    if (getComputedStyle(box).position === "static") box.style.position = "relative";
  });

  // ---------- Base URLs ----------
  const ROOT = "/" + (location.pathname.split("/").filter(Boolean)[0] || "");
 const PHP_BASE = API_BASE_URL;


  // ---------- State ----------
  let coins = [];                        // [{id, symbol, name, image}]
  let balances = {};                     // { SYMBOL: amount }
  let selectedFrom = null;               // { id, symbol }
  let selectedTo   = null;               // { id, symbol }
  const priceCache = {};                 // { id: { p, t } }  (USDT price, timestamp)

  // ---------- Init ----------
  (async function init() {
    try {
      await Promise.all([loadBalances(), loadCoins()]);
      bindEvents();
      preselectDefaults();
      updateQuote();
    } catch (e) {
      console.error("[exchange] init error:", e);
      alert("Failed to load the exchange page. Please refresh.");
    }
  })();

  // ---------- Helpers (use function declarations so they're hoisted) ----------
  function getToken() {
    return localStorage.getItem("jwt") || sessionStorage.getItem("jwt") || "";
  }

  function dedupeById(arr) {
    const seen = new Set();
    const out = [];
    for (const c of arr) {
      if (!c || !c.id || seen.has(c.id)) continue;
      seen.add(c.id);
      out.push({
        id: c.id,
        symbol: (c.symbol || "").toUpperCase(),
        name: c.name || c.symbol || c.id,
        image: c.image || "",
      });
    }
    return out;
  }

  function updateBalanceDisplay(which, symbol) {
    const bal = Number(balances[symbol] || 0);
    (which === "from" ? fromBalEl : toBalEl).textContent = bal.toFixed(6);
  }

  function showDropdown(type, query) {
    const listEl = type === "from" ? fromCoinList : toCoinList;
    const q = (query || "").toLowerCase();

    const filtered = coins.filter(c =>
      c.symbol.toLowerCase().includes(q) ||
      c.name.toLowerCase().includes(q)   ||
      c.id.toLowerCase().includes(q)
    ).slice(0, 25);

    listEl.innerHTML = "";
    filtered.forEach(c => {
      const li = document.createElement("li");
      li.style.display = "flex";
      li.style.alignItems = "center";
      li.style.gap = "8px";
      li.style.cursor = "pointer";
      li.innerHTML = `
        ${c.image ? `<img src="${c.image}" alt="${c.symbol}" width="20" height="20" style="border-radius:50%">` : ""}
        <div>
          <div style="font-weight:600">${c.symbol}</div>
          <div style="font-size:12px;color:#666">${c.name}</div>
        </div>
      `;
      li.addEventListener("click", () => selectCoin(type, c, listEl));
      listEl.appendChild(li);
    });
    listEl.style.display = "block";
  }

  function hideDropdown(el) { el.style.display = "none"; }

  function selectCoin(type, coin, listEl) {
    const payload = { id: coin.id, symbol: coin.symbol.toUpperCase() };
    if (type === "from") {
      selectedFrom = payload;
      fromCoinInput.value = payload.symbol;
      updateBalanceDisplay("from", payload.symbol);
    } else {
      selectedTo = payload;
      toCoinInput.value = payload.symbol;
      updateBalanceDisplay("to", payload.symbol);
    }
    hideDropdown(listEl);
    updateQuote();
  }

  function maybeAutoSelect(type, raw) {
    const sym = (raw || "").trim().toUpperCase();
    if (!sym) return;
    const c = coins.find(x => x.symbol === sym);
    if (c) selectCoin(type, c, type === "from" ? fromCoinList : toCoinList);
  }

  // ---------- API: balances ----------
  async function loadBalances() {
    const token = getToken();
    if (!token) { balances = {}; return; }

    try {
      const r = await axios.get(`${PHP_BASE}/get_balances.php`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const m = r.data?.balances || {};
      balances = {};
      Object.keys(m).forEach(k => (balances[k.toUpperCase()] = Number(m[k])));
    } catch {
      // legacy single balance (USDT only)
      try {
        const r2 = await axios.get(`${PHP_BASE}/get_balance.php`, {
          headers: { Authorization: `Bearer ${token}` },
        });
        balances = { USDT: Number(r2.data?.balance ?? 0) };
      } catch {
        balances = {};
      }
    }
  }

  // ---------- API: coins (via PHP proxy) ----------
  async function loadCoins() {
    const pages = [1, 2, 3, 4, 5]; // up to 1250 coins
    const all = [];
    for (const p of pages) {
      try {
        const r = await axios.get(`${PHP_BASE}/coins_proxy.php?page=${p}`);
        if (Array.isArray(r.data)) all.push(...r.data);
      } catch (e) {
        console.warn(`[exchange] coins_proxy page ${p} failed`, e?.response?.status || e);
      }
    }
    if (!all.length) {
      // small fallback
      all.push(
        { id: "bitcoin",     symbol: "BTC",  name: "Bitcoin",  image: "" },
        { id: "ethereum",    symbol: "ETH",  name: "Ethereum", image: "" },
        { id: "tether",      symbol: "USDT", name: "Tether",   image: "" },
        { id: "binancecoin", symbol: "BNB",  name: "BNB",      image: "" },
        { id: "solana",      symbol: "SOL",  name: "Solana",   image: "" }
      );
    }
    coins = dedupeById(all);
  }

  // ---------- API: price (via PHP proxy) ----------
  async function fetchPrice(id) {
    const c = priceCache[id];
    if (c && Date.now() - c.t < 30_000) return c.p;

    const r = await axios.get(`${PHP_BASE}/price_proxy.php?coin=${encodeURIComponent(id)}`);
    const p = Number(r.data?.price_in_usdt);
    if (!isFinite(p) || p <= 0) throw new Error("Price unavailable");
    priceCache[id] = { p, t: Date.now() };
    return p;
  }

  // ---------- Defaults ----------
  function preselectDefaults() {
    // From: USDT if present; else first non-zero balance
    let fromSym = balances.USDT !== undefined ? "USDT" : null;
    if (!fromSym) {
      const nz = Object.entries(balances).find(([s, a]) => Number(a) > 0);
      if (nz) fromSym = nz[0];
    }
    if (fromSym) {
      const c = coins.find(x => x.symbol === fromSym) ||
                (fromSym === "USDT" ? coins.find(x => x.id === "tether") : null);
      if (c) {
        selectedFrom = { id: c.id, symbol: c.symbol };
        fromCoinInput.value = c.symbol;
        updateBalanceDisplay("from", c.symbol);
      }
    }

    // To: BTC (or ETH); ensure different from From
    let toC = coins.find(x => x.symbol === "BTC") || coins.find(x => x.symbol === "ETH") || coins[0];
    if (toC && selectedFrom && toC.id === selectedFrom.id) {
      toC = coins.find(x => x.id !== selectedFrom.id) || toC;
    }
    if (toC) {
      selectedTo = { id: toC.id, symbol: toC.symbol };
      toCoinInput.value = toC.symbol;
      updateBalanceDisplay("to", toC.symbol);
    }
  }

  // ---------- Events ----------
  function bindEvents() {
    fromCoinInput.addEventListener("focus", () => showDropdown("from", ""));
    toCoinInput.addEventListener("focus",   () => showDropdown("to",   ""));

    fromCoinInput.addEventListener("input", (e) => {
      showDropdown("from", e.target.value);
      maybeAutoSelect("from", e.target.value);
    });
    toCoinInput.addEventListener("input",   (e) => {
      showDropdown("to", e.target.value);
      maybeAutoSelect("to", e.target.value);
    });

    fromCoinInput.addEventListener("blur", () => setTimeout(() => hideDropdown(fromCoinList), 150));
    toCoinInput.addEventListener("blur",   () => setTimeout(() => hideDropdown(toCoinList),   150));

    fromAmount.addEventListener("input", updateQuote);
    convertBtn.addEventListener("click", onConvert);
  }

  // ---------- Quote ----------
  async function updateQuote() {
    if (!selectedFrom || !selectedTo) {
      rateEl.textContent = "Rate: Select coins to see rate";
      toAmount.value = "";
      return;
    }
    if (selectedFrom.id === selectedTo.id) {
      rateEl.textContent = "Rate: 1:1 (Same coin)";
      const v = parseFloat(fromAmount.value);
      toAmount.value = isFinite(v) && v > 0 ? v.toFixed(8) : "";
      return;
    }

    try {
      const [fp, tp] = await Promise.all([fetchPrice(selectedFrom.id), fetchPrice(selectedTo.id)]);
      const rate = fp / tp;
      rateEl.textContent = `Rate: 1 ${selectedFrom.symbol} = ${rate.toFixed(8)} ${selectedTo.symbol}`;
      const fa = parseFloat(fromAmount.value);
      toAmount.value = isFinite(fa) && fa > 0 ? (fa * rate).toFixed(8) : "";
    } catch {
      rateEl.textContent = "Rate: Unable to fetch prices";
      toAmount.value = "";
    }
  }

  // ---------- Convert ----------
  async function onConvert() {
    if (!selectedFrom || !selectedTo) { alert("Select both coins first."); return; }
    if (selectedFrom.id === selectedTo.id) { alert("Please select two different coins."); return; }

    const amount = parseFloat(fromAmount.value);
    if (!isFinite(amount) || amount <= 0) { alert("Enter a valid amount."); return; }

    const available = Number(balances[selectedFrom.symbol] || 0);
    if (amount > available) {
      alert(`Insufficient balance. Available: ${available.toFixed(6)} ${selectedFrom.symbol}`);
      return;
    }

    const token = getToken();
    if (!token) { alert("Session expired. Please log in again."); return; }

    convertBtn.disabled = true;
    const originalText = convertBtn.textContent;
    convertBtn.textContent = "Processing...";

    try {
      // Your backend filename is exchange_processor.php (not exchange_process.php)
      const form = new FormData();
      form.append("from_id",  selectedFrom.id);
      form.append("from_sym", selectedFrom.symbol);
      form.append("to_id",    selectedTo.id);
      form.append("to_sym",   selectedTo.symbol);
      form.append("amount",   amount.toString());

      const resp = await axios.post(`${PHP_BASE}/exchange_processor.php`, form, {
        headers: { Authorization: `Bearer ${token}` },
      });

      if (resp.data?.success) {
        alert(`Success! Converted ${amount} ${selectedFrom.symbol} to ${resp.data.converted} ${selectedTo.symbol}.`);
        window.location.href = "assets.html"; // go to assets after OK
      } else {
        alert(resp.data?.error || "Conversion failed. Please try again.");
      }
    } catch (err) {
      console.error("[exchange] convert error:", err);
      alert(err?.response?.data?.error || err?.message || "Conversion failed. Please try again.");
    } finally {
      convertBtn.disabled = false;
      convertBtn.textContent = originalText;
    }
  }
});
