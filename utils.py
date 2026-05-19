-----# utils.py
import pandas as pd
from datetime import datetime, timedelta
import pymysql

# DB config -- matches your phpMyAdmin settings (no password, port 3307)
DB_CONFIG = {
    "host": "127.0.0.1",
    "user": "root",
    "password": "",    # phpMyAdmin uses no password in your setup
    "db": "medical_inventory",
    "cursorclass": pymysql.cursors.DictCursor,
    "charset": "utf8mb4",
    "port": 3307
}

TABLE_NAME = "retailer_customer_sales"

# Candidate names for columns (ordered by preference)
DATE_CANDIDATES = [
    "transaction_time", "sale_time", "sale_date", "created_at", "date", "timestamp", "datetime"
]
ITEM_CANDIDATES = [
    "item_id", "medicine_id", "product_id", "med_id", "medicineId", "productId"
]
QTY_CANDIDATES = [
    "quantity", "qty", "sold_qty", "amount", "quantity_sold"
]

def _detect_columns(conn):
    """Detects date, item id, and qty column names in TABLE_NAME."""
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
            "WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            (DB_CONFIG["db"], TABLE_NAME)
        )
        cols = [r["COLUMN_NAME"] for r in cur.fetchall()]

    def find_first(candidates):
        for c in candidates:
            if c in cols:
                return c
        return None

    date_col = find_first(DATE_CANDIDATES)
    item_col = find_first(ITEM_CANDIDATES)
    qty_col = find_first(QTY_CANDIDATES)

    return date_col, item_col, qty_col, cols

def get_item_timeseries(item_id, days_back=60):
    """
    Returns a DataFrame with columns ['ds','qty'] for last `days_back` days for given item_id.
    The function auto-detects appropriate column names in retailer_customer_sales.
    """
    conn = pymysql.connect(**DB_CONFIG)
    try:
        date_col, item_col, qty_col, all_cols = _detect_columns(conn)

        if not date_col or not item_col or not qty_col:
            missing = []
            if not date_col: missing.append("date column")
            if not item_col: missing.append("item id column")
            if not qty_col: missing.append("quantity column")
            raise Exception(
                "Could not auto-detect columns in table '{}'. Missing: {}".format(
                    TABLE_NAME, ", ".join(missing)
                ) + ". Available columns: " + ", ".join(all_cols)
            )

        # Build SQL safely using validated column names (these are from INFORMATION_SCHEMA)
        query = f"""
            SELECT DATE({date_col}) AS ds, SUM({qty_col}) AS qty
            FROM {TABLE_NAME}
            WHERE {item_col} = %s
              AND {date_col} >= DATE_SUB(CURDATE(), INTERVAL %s DAY)
            GROUP BY DATE({date_col})
            ORDER BY ds
        """

        with conn.cursor() as cur:
            cur.execute(query, (item_id, days_back))
            rows = cur.fetchall()
    finally:
        conn.close()

    df = pd.DataFrame(rows)
    if df.empty:
        start = (datetime.utcnow().date() - timedelta(days=days_back-1))
        dates = [start + timedelta(days=i) for i in range(days_back)]
        return pd.DataFrame({"ds": dates, "qty": [0]*days_back})
    df['ds'] = pd.to_datetime(df['ds']).dt.date

    start = df['ds'].min()
    end = datetime.utcnow().date()

    full = pd.DataFrame({"ds": pd.date_range(start=start, end=end)})
    full['ds'] = full['ds'].dt.date

    merged = full.merge(df, on='ds', how='left').fillna(0)
    merged = merged.tail(days_back).reset_index(drop=True)
    # ensure 'qty' column exists (if INFORMATION_SCHEMA gave different alias)
    if 'qty' not in merged.columns and qty_col in merged.columns:
        merged = merged.rename(columns={qty_col: 'qty'})
    return merged

def moving_average_forecast(df, window=7, periods=7):
    """
    Simple moving average forecast:
      - mean of last `window` days used as constant prediction for next `periods` days
    """
    if df.empty:
        mean_val = 0.0
    else:
        series = df['qty'].astype(float).values
        mean_val = float(series[-window:].mean()) if len(series) else 0.0

    last_date = df['ds'].max() if not df.empty else datetime.utcnow().date()
    preds = []
    for i in range(1, periods+1):
        day = last_date + timedelta(days=i)
        preds.append({
            "ds": day.isoformat(),
            "predicted": round(mean_val, 4)
        })
    return preds
