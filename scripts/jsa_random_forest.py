import json
import math
import sys
from datetime import date, timedelta

import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error


MIN_DAYS = 60
MIN_ACTIVE_DAYS = 14
HOLDOUT_DAYS = 14
MAX_DAYS = 400


def insufficient(days, active):
    return {
        "status": "insufficient_data",
        "forecast": {
            "status": "insufficient_data",
            "method": None,
            "values": [],
            "observed_calendar_days": days,
            "observed_active_days": active,
            "minimum_calendar_days": MIN_DAYS,
            "minimum_active_days": MIN_ACTIVE_DAYS,
        },
        "model_evaluation": None,
    }


def metrics(actual, predicted):
    return {
        "mae": round(
            float(mean_absolute_error(actual, predicted)), 3
        ),
        "rmse": round(
            math.sqrt(mean_squared_error(actual, predicted)), 3
        ),
    }


def evaluate(payload):
    if not isinstance(payload, dict):
        raise ValueError("Input harus berupa objek JSON.")

    trend = payload.get("sales_trend")

    if not isinstance(trend, list):
        raise ValueError("sales_trend harus berupa array.")

    if len(trend) > MAX_DAYS:
        raise ValueError("Jumlah hari melebihi batas.")

    dates = []
    quantities = []

    for row in trend:
        if not isinstance(row, dict):
            raise ValueError("Setiap baris harus berupa objek.")

        raw_date = row.get("date")
        qty = row.get("qty")

        if not isinstance(raw_date, str):
            raise ValueError("Tanggal tidak valid.")

        parsed_date = date.fromisoformat(raw_date)

        if parsed_date.isoformat() != raw_date:
            raise ValueError("Format tanggal harus YYYY-MM-DD.")

        if type(qty) is not int or qty < 0 or qty > 1000000:
            raise ValueError("qty harus bilangan bulat nonnegatif.")

        if dates and parsed_date != dates[-1] + timedelta(days=1):
            raise ValueError(
                "Tanggal harus berurutan tanpa duplikasi atau jeda."
            )

        dates.append(parsed_date)
        quantities.append(qty)

    days = len(quantities)
    active_days = sum(qty > 0 for qty in quantities)

    if days < MIN_DAYS or active_days < MIN_ACTIVE_DAYS:
        return insufficient(days, active_days)

    features = []
    targets = []

    for index in range(7, days):
        lag = [
            quantities[index - previous]
            for previous in range(1, 8)
        ]

        features.append(
            lag + [
                sum(lag) / 7,
                dates[index].weekday(),
            ]
        )

        targets.append(quantities[index])

    X = np.asarray(features, dtype=float)
    y = np.asarray(targets, dtype=float)

    X_train = X[:-HOLDOUT_DAYS]
    y_train = y[:-HOLDOUT_DAYS]

    X_test = X[-HOLDOUT_DAYS:]
    y_test = y[-HOLDOUT_DAYS:]

    model = RandomForestRegressor(
        n_estimators=120,
        min_samples_leaf=2,
        random_state=42,
        n_jobs=1,
    )

    model.fit(X_train, y_train)

    rf_pred = model.predict(X_test)
    naive_pred = X_test[:, 0]

    weights = np.arange(7, 0, -1, dtype=float)
    wma_pred = (X_test[:, :7] @ weights) / weights.sum()

    scores = {
        "random_forest": metrics(y_test, rf_pred),
        "wma_7": metrics(y_test, wma_pred),
        "naive": metrics(y_test, naive_pred),
    }

    best_method = min(
        scores,
        key=lambda method: scores[method]["mae"],
    )

    # Latih ulang menggunakan seluruh histori yang tersedia
    # untuk memprediksi satu hari setelah tanggal terakhir.
    model.fit(X, y)

    next_date = dates[-1] + timedelta(days=1)

    next_lags = [
        quantities[-previous]
        for previous in range(1, 8)
    ]

    next_features = np.asarray(
        [
            next_lags + [
                sum(next_lags) / 7,
                next_date.weekday(),
            ]
        ],
        dtype=float,
    )

    raw_prediction = max(
        0.0,
        float(model.predict(next_features)[0]),
    )

    return {
        "status": "experimental",
        "forecast": {
            "status": "experimental",
            "method": "RandomForestRegressor",
            "values": [
                {
                    "date": next_date.isoformat(),
                    "qty": int(math.floor(raw_prediction + 0.5)),
                    "raw_qty": round(raw_prediction, 2),
                }
            ],
            "observed_calendar_days": days,
            "observed_active_days": active_days,
            "message": (
                "Prediksi eksperimental. "
                "Tidak digunakan untuk restok otomatis."
            ),
        },
        "model_evaluation": {
            "method": "chronological_holdout_one_step",
            "training_samples": len(X_train),
            "evaluation_days": HOLDOUT_DAYS,
            "metrics": scores,
            "best_by_mae": best_method,
            "note": (
                "Fitur evaluasi memakai histori aktual sebelumnya. "
                "Belum merupakan recursive multi-step forecast."
            ),
        },
    }


def main():
    raw = sys.stdin.read(262145)

    if len(raw) > 262144:
        raise ValueError("Payload terlalu besar.")

    payload = json.loads(raw)
    result = evaluate(payload)

    print(json.dumps(result, allow_nan=False))


if __name__ == "__main__":
    try:
        main()
    except (ValueError, KeyError, TypeError, json.JSONDecodeError) as error:
        print(
            json.dumps({
                "status": "invalid_data",
                "message": str(error),
            })
        )
        sys.exit(2)
    except Exception:
        print(
            json.dumps({
                "status": "execution_error",
                "message": "Engine gagal memproses input.",
            })
        )
        sys.exit(1)
