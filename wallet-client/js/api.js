// api.js
import axios from "axios";

export function makeApi(jwt) {
  const api = axios.create({
    baseURL: "/wallet-server/user/v1", // adjust if your PHP lives elsewhere
    headers: { "Content-Type": "application/json" },
  });

  api.interceptors.request.use((cfg) => {
    cfg.headers = cfg.headers || {};
    cfg.headers.Authorization = `Bearer ${jwt}`;
    return cfg;
  });

  const ok = (p) => p.then((r) => {
    if (r.data?.ok) return r.data.data;
    // some legacy endpoints may not wrap with {ok:true}
    if (r.data?.data) return r.data.data;
    throw new Error(r.data?.error || "API error");
  });

  return {
    // ERC-20 (on-chain) helpers
    getMeta:        ()         => ok(api.get("/erc20/meta")),
    getBalance:     (addr)     => ok(api.get(`/erc20/balance/${addr}`)),
    buildTransfer:  (from,to,amount) => ok(api.post("/erc20/transfer", { from, to, amount })),
    buildApprove:   (owner,spender,amount) => ok(api.post("/erc20/approve", { owner, spender, amount })),
    // optional: record on-chain tx in your DB history
    recordOnchainTx: (txHash)  => ok(api.post("/erc20_record.php", { txHash })),
  };
}
