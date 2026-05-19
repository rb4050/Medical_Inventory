# app.py
from flask import Flask, request, jsonify
from utils import get_item_timeseries, moving_average_forecast
from datetime import datetime

app = Flask(__name__)

@app.route("/predict", methods=["POST"])
def predict():
    data = request.get_json() or {}
    item_id = data.get("item_id")
    periods = int(data.get("periods", 7))
    window = int(data.get("window", 7))
    days_back = int(data.get("days_back", 60))

    if not item_id:
        return jsonify({"error": "item_id required"}), 400

    try:
        df = get_item_timeseries(item_id, days_back)
        preds = moving_average_forecast(df, window, periods)
        return jsonify({
            "item_id": item_id,
            "generated_at": datetime.utcnow().isoformat() + "Z",
            "forecast": preds
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=False)
