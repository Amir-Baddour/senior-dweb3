# python/app.py
from flask import Flask, request, jsonify
from flask_cors import CORS
import requests, time

app = Flask(__name__)
CORS(app)

# simple in-memory cache: { page: (timestamp, data) }
COINS_CACHE = {}
CACHE_TTL_SECONDS = 120  # cache each page for 2 minutes
USER_AGENT = "Wallet-App/1.0 (+local)"

def fetch_coins_page(page: int, retries: int = 2, backoff: float = 0.6):
    """Fetch one page from CoinGecko with retries + timeout."""
    url = "https://api.coingecko.com/api/v3/coins/markets"
    params = {
        "vs_currency": "usd",
        "order": "market_cap_desc",
        "per_page": 250,
        "page": page
    }
    headers = {"User-Agent": USER_AGENT}
    for attempt in range(retries + 1):
        try:
            res = requests.get(url, params=params, headers=headers, timeout=10)
            if res.status_code == 200:
                return res.json()
            # Backoff on non-200 (rate limit or server hiccup)
            time.sleep(backoff * (attempt + 1))
        except requests.RequestException:
            time.sleep(backoff * (attempt + 1))
    return None

@app.get("/coins")
def get_top_coins():
    try:
        page = request.args.get("page", default=1, type=int)
        now = time.time()

        # Serve from cache if fresh
        ts, data = COINS_CACHE.get(page, (0, None))
        if data is not None and (now - ts) < CACHE_TTL_SECONDS:
            return jsonify(data)

        # Fetch from upstream with retries
        coins = fetch_coins_page(page)
        if not isinstance(coins, list):
            # Upstream failed (rate limit or network). Return empty list to avoid 500 spam.
            empty = []
            COINS_CACHE[page] = (now, empty)  # cache empty to throttle requests
            return jsonify(empty)

        # Normalize + cache
        result = [
            {
                "id": c.get("id"),
                "symbol": (c.get("symbol") or "").upper(),
                "name": c.get("name"),
                "image": c.get("image"),
            }
            for c in coins
            if isinstance(c, dict)
        ]
        COINS_CACHE[page] = (now, result)
        return jsonify(result)
    except Exception as e:
        # Final safety: never 500 the UI for a list endpoint
        return jsonify([])

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)
