# python/price_app.py
from flask import Flask, request, jsonify
from flask_cors import CORS
import requests

app = Flask(__name__)
CORS(app)

@app.get("/price")
def price():
    coin = request.args.get("coin", "").strip()
    if not coin:
        return jsonify({"error": "missing coin param"}), 400

    try:
        r = requests.get(
            "https://api.coingecko.com/api/v3/simple/price",
            params={"ids": coin, "vs_currencies": "usd"},
            timeout=10,
            headers={"User-Agent": "Wallet-App/1.0"}
        )
        if r.status_code != 200:
            return jsonify({"error": f"upstream {r.status_code}"}), 502

        data = r.json()
        usd = data.get(coin, {}).get("usd")
        if usd is None:
            return jsonify({"error": "coin not found"}), 404

        # treat USD as USDT proxy
        return jsonify({"price_in_usdt": float(usd)})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5001, debug=True)
