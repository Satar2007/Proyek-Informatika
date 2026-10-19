
"""
JIMNY SMART ASSISTANT
MODUL MACHINE LEARNING: RANDOM FOREST REGRESSION

TUJUAN PROGRAM:
1. Menerima data historis penjualan harian.
2. Memeriksa kelengkapan dan validitas data.
3. Membentuk fitur (X) dan label/target (y) secara otomatis.
4. Melatih Random Forest Regression menggunakan data historis.
5. Menguji hasil prediksi terhadap data aktual.
6. Membandingkan Random Forest, Weighted Moving Average,
   dan metode prediksi sederhana (Naive).
7. Menghasilkan prediksi penjualan untuk hari berikutnya.

METODE:
Supervised Machine Learning - Regression.

LABEL:
Jumlah porsi terjual yang sebenarnya pada hari target.

BATASAN:
Random Forest masih bersifat eksperimental/offline.
Hasilnya tidak digunakan untuk restok otomatis.
"""

# ============================================================
# BAGIAN 1 - IMPORT LIBRARY
# ============================================================
# TUJUAN:
# Menyediakan library untuk membaca JSON, mengolah tanggal,
# menghitung metrik kesalahan, serta menjalankan Random Forest.

import json
import math
import sys
from datetime import date, timedelta

import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error


# ============================================================
# BAGIAN 2 - KONFIGURASI BATAS DATA
# ============================================================
# TUJUAN:
# Menentukan persyaratan minimum data sebelum model dilatih.
#
# MIN_DAYS:
# Minimal 60 hari kalender historis.
#
# MIN_ACTIVE_DAYS:
# Minimal 14 hari yang mempunyai penjualan lebih dari nol.
#
# HOLDOUT_DAYS:
# Sebanyak 14 sampel terakhir digunakan untuk evaluasi model.
#
# MAX_DAYS:
# Maksimal menerima 400 hari historis untuk membatasi input.

MIN_DAYS = 60
MIN_ACTIVE_DAYS = 14
HOLDOUT_DAYS = 14
MAX_DAYS = 400


# ============================================================
# BAGIAN 3 - PENANGANAN DATA TIDAK MENCUKUPI
# ============================================================
# TUJUAN:
# Memberikan informasi bahwa prediksi belum dapat dilakukan
# jika data historis tidak memenuhi persyaratan.
#
# Contoh:
# Hanya tersedia 4 hari aktif dari minimal 14 hari aktif.
# Program tidak memaksakan model untuk menghasilkan prediksi.

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


# ============================================================
# BAGIAN 4 - PENGUKURAN KESALAHAN PREDIKSI
# ============================================================
# TUJUAN:
# Mengukur seberapa jauh prediksi model dari penjualan aktual.
#
# MAE (Mean Absolute Error):
# Rata-rata selisih absolut prediksi dengan nilai aktual.
#
# RMSE (Root Mean Squared Error):
# Akar rata-rata kuadrat kesalahan prediksi.
# Kesalahan yang besar diberi pengaruh lebih besar.
#
# Semakin kecil MAE dan RMSE, semakin kecil kesalahan model
# pada data evaluasi yang digunakan.

def metrics(actual, predicted):
    return {
        "mae": round(
            float(mean_absolute_error(actual, predicted)), 3
        ),
        "rmse": round(
            math.sqrt(mean_squared_error(actual, predicted)), 3
        ),
    }


# ============================================================
# BAGIAN 5 - MEMULAI PROSES EVALUASI MODEL
# ============================================================
# TUJUAN:
# Menerima data penjualan yang dikirim melalui format JSON.
#
# Struktur data masukan yang diharapkan:
#
# {
#     "sales_trend": [
#         {"date": "2026-10-01", "qty": 12},
#         {"date": "2026-10-02", "qty": 15}
#     ]
# }
#
# Ini hanya contoh format, bukan dataset lengkap.
# Program memerlukan histori yang memenuhi batas minimum.
#
# PENTING:
# File Python ini tidak mengambil data langsung dari MySQL.
# Data disediakan melalui payload JSON.

def evaluate(payload):

    # Memastikan input utama berupa objek/dictionary.
    if not isinstance(payload, dict):
        raise ValueError("Input harus berupa objek JSON.")

    # Mengambil daftar histori penjualan.
    trend = payload.get("sales_trend")

    # Memastikan histori berupa array/list.
    if not isinstance(trend, list):
        raise ValueError("sales_trend harus berupa array.")

    # Membatasi jumlah hari yang dapat diproses.
    if len(trend) > MAX_DAYS:
        raise ValueError("Jumlah hari melebihi batas.")

    # Menyiapkan tempat penyimpanan tanggal dan penjualan.
    dates = []
    quantities = []

    # ========================================================
    # BAGIAN 6 - VALIDASI DAN PEMBERSIHAN DATA
    # ========================================================
    # TUJUAN:
    # Memastikan data historis layak digunakan untuk model.
    #
    # Pemeriksaan:
    # 1. Setiap baris harus mempunyai format yang benar.
    # 2. Tanggal harus valid dan berformat YYYY-MM-DD.
    # 3. Penjualan harus bilangan bulat dan tidak negatif.
    # 4. Tanggal harus berurutan tanpa duplikasi atau jeda.
    #
    # Validasi ini penting untuk menjaga kualitas dataset.

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

    # Menghitung jumlah hari kalender historis.
    days = len(quantities)

    # Menghitung hari yang memiliki penjualan di atas nol.
    active_days = sum(qty > 0 for qty in quantities)

    # Jangan melakukan training jika data belum mencukupi.
    if days < MIN_DAYS or active_days < MIN_ACTIVE_DAYS:
        return insufficient(days, active_days)

    # ========================================================
    # BAGIAN 7 - FEATURE ENGINEERING DAN PELABELAN DATA
    # ========================================================
    # INI BAGIAN PENTING UNTUK PENJELASAN KEPADA DOSEN.
    #
    # TUJUAN:
    # Mengubah deret waktu penjualan menjadi dataset
    # supervised learning yang terdiri dari:
    #
    # X = Features / fitur input
    # y = Target / label penjualan aktual
    #
    # FITUR YANG DIGUNAKAN:
    # 1. Penjualan 1 hari sebelumnya.
    # 2. Penjualan 2 hari sebelumnya.
    # 3. Penjualan 3 hari sebelumnya.
    # 4. Penjualan 4 hari sebelumnya.
    # 5. Penjualan 5 hari sebelumnya.
    # 6. Penjualan 6 hari sebelumnya.
    # 7. Penjualan 7 hari sebelumnya.
    # 8. Rata-rata penjualan tujuh hari sebelumnya.
    # 9. Hari dalam minggu dari tanggal target.
    #
    # LABEL YANG DIGUNAKAN:
    # Jumlah penjualan aktual pada hari target.
    #
    # CONTOH:
    # Penjualan tujuh hari sebelumnya:
    # [12, 10, 15, 13, 14, 11, 16]
    #
    # Rata-rata tujuh hari = 13
    # Hari target = Senin
    #
    # X = [12, 10, 15, 13, 14, 11, 16, 13, 0]
    # y = 18 (misalnya penjualan aktual hari Senin)
    #
    # Catatan:
    # Ilustrasi X mengikuti urutan lag dari H-1 hingga H-7.
    #
    # Label tidak perlu dimasukkan secara manual.
    # Label dibentuk dari qty aktual dalam data historis.

    features = []
    targets = []

    # Mulai dari index 7 agar tersedia tujuh hari sebelumnya.
    for index in range(7, days):

        # Mengambil penjualan 1 sampai 7 hari sebelum target.
        lag = [
            quantities[index - previous]
            for previous in range(1, 8)
        ]

        # Membentuk sembilan fitur input.
        features.append(
            lag + [
                sum(lag) / 7,
                dates[index].weekday(),
            ]
        )

        # PELABELAN OTOMATIS:
        # qty pada tanggal target menjadi jawaban aktual (y).
        #
        # Misalnya:
        # X berisi histori sebelum 19 Oktober.
        # y berisi penjualan aktual 19 Oktober.
        targets.append(quantities[index])

    # ========================================================
    # BAGIAN 8 - KONVERSI DATA KE NUMPY
    # ========================================================
    # TUJUAN:
    # Mengubah features dan labels menjadi array numerik
    # yang dapat diproses oleh Scikit-learn.
    #
    # X merupakan matriks fitur.
    # y merupakan vektor label aktual.

    X = np.asarray(features, dtype=float)
    y = np.asarray(targets, dtype=float)

    # ========================================================
    # BAGIAN 9 - PEMBAGIAN DATA TRAINING DAN TESTING
    # ========================================================
    # TUJUAN:
    # Memisahkan data untuk pelatihan dan evaluasi.
    #
    # Data awal digunakan sebagai data training.
    # Empat belas sampel terakhir digunakan sebagai data test.
    #
    # Pembagian dilakukan berdasarkan urutan waktu,
    # bukan secara acak, untuk menjaga urutan historis.
    #
    # Ini penting karena penjualan merupakan data deret waktu.
    #
    # Data test memiliki label aktual yang sudah diketahui,
    # tetapi label tersebut tidak digunakan melatih model
    # sebelum evaluasi dilakukan.

    X_train = X[:-HOLDOUT_DAYS]
    y_train = y[:-HOLDOUT_DAYS]

    X_test = X[-HOLDOUT_DAYS:]
    y_test = y[-HOLDOUT_DAYS:]

    # ========================================================
    # BAGIAN 10 - MEMBANGUN MODEL RANDOM FOREST
    # ========================================================
    # TUJUAN:
    # Membuat model Machine Learning untuk regresi.
    #
    # Random Forest menggabungkan sejumlah Decision Tree.
    #
    # PARAMETER:
    #
    # n_estimators=120:
    # Menggunakan 120 pohon keputusan.
    #
    # min_samples_leaf=2:
    # Setiap daun pada pohon minimal berisi dua sampel.
    #
    # random_state=42:
    # Menetapkan seed agar eksperimen dapat direproduksi.
    #
    # n_jobs=1:
    # Membatasi pengerjaan ke satu worker.

    model = RandomForestRegressor(
        n_estimators=120,
        min_samples_leaf=2,
        random_state=42,
        n_jobs=1,
    )

    # ========================================================
    # BAGIAN 11 - TRAINING MODEL
    # ========================================================
    # TUJUAN:
    # Melatih Random Forest menggunakan fitur historis
    # dan label penjualan aktual dari data training.
    #
    # fit(X_train, y_train):
    # X_train = data masukan untuk proses belajar.
    # y_train = jawaban aktual yang dipelajari model.

    model.fit(X_train, y_train)

    # ========================================================
    # BAGIAN 12 - PREDIKSI PADA DATA TEST
    # ========================================================
    # TUJUAN:
    # Memeriksa kemampuan model terhadap 14 sampel terakhir
    # yang tidak digunakan dalam training awal.

    rf_pred = model.predict(X_test)

    # Prediksi Naive:
    # Menggunakan penjualan satu hari sebelumnya sebagai
    # perkiraan penjualan pada hari target.
    naive_pred = X_test[:, 0]

    # Weighted Moving Average tujuh hari:
    # Menggunakan bobot 7, 6, 5, 4, 3, 2, 1.
    # Data terbaru mempunyai pengaruh lebih besar.
    weights = np.arange(7, 0, -1, dtype=float)

    # Menghitung prediksi Weighted Moving Average.
    wma_pred = (X_test[:, :7] @ weights) / weights.sum()

    # ========================================================
    # BAGIAN 13 - EVALUASI DAN PERBANDINGAN MODEL
    # ========================================================
    # TUJUAN:
    # Membandingkan tiga pendekatan prediksi:
    #
    # 1. Random Forest Regression.
    # 2. Weighted Moving Average tujuh hari.
    # 3. Naive baseline.
    #
    # Semua model dibandingkan dengan penjualan aktual.
    #
    # Evaluasi menggunakan:
    # - Mean Absolute Error (MAE).
    # - Root Mean Squared Error (RMSE).

    scores = {
        "random_forest": metrics(y_test, rf_pred),
        "wma_7": metrics(y_test, wma_pred),
        "naive": metrics(y_test, naive_pred),
    }

    # Memilih metode dengan MAE paling rendah.
    #
    # Hasil ini hanya untuk evaluasi.
    # Tidak otomatis mengganti algoritma Laravel
    # atau mengaktifkan keputusan restok.
    best_method = min(
        scores,
        key=lambda method: scores[method]["mae"],
    )

    # ========================================================
    # BAGIAN 14 - MELATIH ULANG MENGGUNAKAN SELURUH DATA
    # ========================================================
    # TUJUAN:
    # Setelah evaluasi selesai, Random Forest dilatih lagi
    # menggunakan seluruh data historis yang tersedia.
    #
    # Tujuannya memanfaatkan lebih banyak data untuk
    # menghasilkan prediksi eksperimental hari berikutnya.

    model.fit(X, y)

    # Menghitung tanggal satu hari setelah histori terakhir.
    next_date = dates[-1] + timedelta(days=1)

    # Mengambil tujuh penjualan terakhir sebagai input baru.
    next_lags = [
        quantities[-previous]
        for previous in range(1, 8)
    ]

    # Membentuk fitur untuk prediksi hari berikutnya.
    next_features = np.asarray(
        [
            next_lags + [
                sum(next_lags) / 7,
                next_date.weekday(),
            ]
        ],
        dtype=float,
    )

    # ========================================================
    # BAGIAN 15 - MENGHASILKAN PREDIKSI AKHIR
    # ========================================================
    # TUJUAN:
    # Menghasilkan perkiraan penjualan untuk satu hari ke depan.
    #
    # max(0.0, ...):
    # Mencegah hasil akhir menjadi negatif.
    #
    # Hasil mentah berupa angka desimal.
    # Angka tersebut nantinya dibulatkan menjadi jumlah porsi.

    raw_prediction = max(
        0.0,
        float(model.predict(next_features)[0]),
    )

    # ========================================================
    # BAGIAN 16 - MENYIAPKAN HASIL JSON
    # ========================================================
    # TUJUAN:
    # Mengembalikan hasil prediksi dan evaluasi.
    #
    # OUTPUT FORECAST:
    # Tanggal prediksi, metode, jumlah porsi, dan status.
    #
    # OUTPUT MODEL_EVALUATION:
    # Jumlah data training, lama evaluasi, MAE, RMSE,
    # serta metode terbaik berdasarkan MAE.
    #
    # Status experimental menandakan bahwa hasil prediksi
    # belum digunakan untuk pengambilan keputusan otomatis.

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


# ============================================================
# BAGIAN 17 - MEMBACA INPUT PROGRAM
# ============================================================
# TUJUAN:
# Menerima data JSON dari standard input (stdin).
#
# Payload dibatasi sekitar 256 KiB agar tidak terlalu besar.
#
# Setelah input dibaca, fungsi evaluate() dijalankan
# dan hasilnya dikembalikan dalam format JSON.

def main():
    raw = sys.stdin.read(262145)

    if len(raw) > 262144:
        raise ValueError("Payload terlalu besar.")

    payload = json.loads(raw)
    result = evaluate(payload)

    print(json.dumps(result, allow_nan=False))


# ============================================================
# BAGIAN 18 - MENANGANI ERROR
# ============================================================
# TUJUAN:
# Mencegah kegagalan menghasilkan output yang tidak terstruktur.
#
# invalid_data:
# Data input tidak sesuai format atau persyaratan.
#
# execution_error:
# Terjadi kesalahan tidak terduga ketika engine berjalan.
#
# Exit code:
# 0 = selesai tanpa error.
# 2 = input tidak valid.
# 1 = kegagalan eksekusi internal.

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
