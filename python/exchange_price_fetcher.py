import requests

def fetch_price(coin):
    url = "https://api.coingecko.com/api/v3/simple/price"
    params = {
        'ids': coin,
        'vs_currencies': 'usd'  # use 'usd' as proxy for 'usdt'
    }
    try:
        response = requests.get(url, params=params)
        response.raise_for_status()
        data = response.json()
        return data.get(coin, {}).get('usd')  # fetch 'usd' price
    except requests.RequestException as e:
        print(f"Error fetching price: {e}")
        return None
