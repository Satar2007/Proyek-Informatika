"""Fase 3 offline: rekomendasi restok satu hari, tanpa menulis database.

Input: JSON Fase 2, snapshot stok JSON, kebijakan eksplisit JSON.
Kuantil memakai residual bertanda, bukan batas interval simetris Fase 2.
"""
import argparse
import csv
import json
import math
import sys
from datetime import date, datetime, timedelta, timezone
from pathlib import Path


class RestockError(ValueError):
    pass


def integer(value, label, minimum=0):
    if type(value) is not int or value < minimum:
        raise RestockError(f'{label} harus integer >= {minimum}.')
    return value


def number(value, label):
    if type(value) not in (int, float) or not math.isfinite(value):
        raise RestockError(f'{label} harus angka finite.')
    return float(value)


def iso_day(value):
    try:
        return date.fromisoformat(value)
    except (ValueError, TypeError):
        raise RestockError('Tanggal harus YYYY-MM-DD.')


def positive_point(value):
    value = number(value, 'point')
    if value < 0:
        raise RestockError('point negatif.')
    return value


def demand_target(point, errors, level, window=60, minimum=20):
    """Kuantil satu sisi dengan rank ceil((n+1)*level); tanpa jaminan coverage."""
    sample = errors[-window:]
    rank = math.ceil((len(sample) + 1) * level)
    if len(sample) < minimum or rank > len(sample):
        return None
    return max(0, math.ceil(point + sorted(sample)[rank - 1]))


def calibrate(rows, forecast_day, level, window, minimum):
    errors, hits = [], []
    previous = None
    for row in rows:
        day = iso_day(row['date'])
        if day >= forecast_day or (previous is not None and day != previous + timedelta(days=1)):
            raise RestockError('Backtest harus berurutan harian dan sebelum tanggal forecast.')
        actual = integer(row['actual'], 'actual') if type(row['actual']) is int else positive_point(row['actual'])
        if actual != math.floor(actual):
            raise RestockError('actual harus porsi bulat.')
        point = positive_point(row['point'])
        target = demand_target(point, errors, level, window, minimum)
        if target is not None:
            hits.append(actual <= target)
        errors.append(actual - point)  # Dicatat SETELAH menguji hari tersebut.
        previous = day
    if rows and previous != forecast_day - timedelta(days=1):
        raise RestockError('Backtest harus berakhir sehari sebelum forecast.')
    return errors, {'n': len(hits), 'coverage': sum(hits) / len(hits) if hits else None}


def run_restock(forecast, snapshot, policy, *, demo=False, today=None):
    today = today or datetime.now(timezone(timedelta(hours=7))).date()
    if forecast['schema_version'] != 1 or forecast['phase'] != 2:
        raise RestockError('Perlu JSON Fase 2 schema_version=1.')
    f = forecast['forecast']
    day = iso_day(f['date'])
    if iso_day(forecast['data']['end']) != day - timedelta(days=1):
        raise RestockError('Tanggal akhir data tidak cocok dengan forecast.')
    if snapshot['schema_version'] != 1 or policy['schema_version'] != 1:
        raise RestockError('Schema snapshot/policy tidak didukung.')
    if snapshot['stock_basis'] != 'available_after_reservations':
        raise RestockError('Snapshot wajib memakai menus.stok (available_after_reservations).')
    if snapshot['timing'] != 'before_service' or iso_day(snapshot['date']) != day:
        raise RestockError('Snapshot harus sebelum pelayanan pada tanggal forecast.')
    if policy['horizon_days'] != 1 or policy['lead_time_days'] != 0:
        raise RestockError('Hanya horizon 1 hari dan restok tiba sebelum pelayanan (lead time 0).')
    if policy['minimum_stock_rule'] != 'floor':
        raise RestockError('minimum_stok dipakai sebagai batas minimum target (floor).')
    if not demo and (day != today or forecast.get('historical_replay') is not False
                     or snapshot['source'] != 'actual'):
        raise RestockError('Mode operasional perlu tanggal hari ini, forecast non-replay, snapshot actual.')
    if snapshot['source'] not in ('actual', 'synthetic'):
        raise RestockError('source snapshot harus actual atau synthetic.')
    window = integer(policy['calibration_window'], 'calibration_window', 20)
    minimum = integer(policy['minimum_calibration'], 'minimum_calibration', 20)
    if minimum > window:
        raise RestockError('minimum_calibration melebihi calibration_window.')
    levels = policy['category_service_levels']
    if not isinstance(levels, dict):
        raise RestockError('category_service_levels harus object.')
    for level in [policy['default_service_level'], *levels.values()]:
        if not 0.5 <= number(level, 'service_level') <= 0.95:
            raise RestockError('service_level harus 0.50 sampai 0.95.')
    stocks, predictions = {}, {}
    for m in snapshot['menus']:
        key = integer(m['menu_id'], 'menu_id', 1)
        if key in stocks:
            raise RestockError('menu_id snapshot duplikat.')
        integer(m['category_id'], 'category_id', 1)
        integer(m['stok'], 'stok')
        integer(m['minimum_stok'], 'minimum_stok')
        if type(m['is_active']) is not bool or not isinstance(m['nama_menu'], str) or not m['nama_menu'].strip():
            raise RestockError('Nama menu dan boolean is_active diperlukan.')
        if m['deleted_at'] is not None and not isinstance(m['deleted_at'], str):
            raise RestockError('deleted_at harus null atau timestamp string.')
        stocks[key] = m
    if not stocks:
        raise RestockError('Snapshot kosong.')
    for m in f['menus']:
        key = integer(m['menu_id'], 'menu_id forecast', 1)
        if key in predictions:
            raise RestockError('menu_id forecast duplikat.')
        positive_point(m['point'])
        predictions[key] = m
    if not predictions:
        raise RestockError('Forecast kosong.')
    rows = []
    for key in sorted(stocks.keys() | predictions.keys()):
        stock, predicted = stocks.get(key), predictions.get(key)
        row = {'menu_id': key, 'nama_menu': (stock or predicted)['nama_menu'],
               'stok': stock['stok'] if stock else None,
               'minimum_stok': stock['minimum_stok'] if stock else None,
               'service_level': None, 'point': predicted['point'] if predicted else None,
               'calibration_n': 0, 'demand_target': None, 'target_stock': None,
               'restock_qty': None, 'evaluation': {'n': 0, 'coverage': None}}
        if stock is None:
            row['status'] = 'missing_snapshot'
        elif not stock['is_active'] or stock['deleted_at'] is not None:
            row['status'] = 'inactive_or_deleted'
            row['restock_qty'] = 0
        elif predicted is None:
            row['status'] = 'missing_forecast'
        else:
            level = levels.get(str(stock['category_id']), policy['default_service_level'])
            row['service_level'] = level
            history = forecast['backtest']['menus'].get(str(key))
            if history is None:
                raise RestockError(f'Backtest menu {key} tidak tersedia.')
            errors, evaluation = calibrate(history, day, level, window, minimum)
            row['evaluation'] = evaluation
            row['calibration_n'] = min(window, len(errors))
            target = demand_target(predicted['point'], errors, level, window, minimum)
            row['demand_target'] = target
            if target is None:
                row['status'] = 'insufficient_calibration'
            else:
                row['target_stock'] = max(stock['minimum_stok'], target)
                row['restock_qty'] = max(0, row['target_stock'] - stock['stok'])
                row['status'] = 'restock' if row['restock_qty'] else 'sufficient'
        rows.append(row)
    return {'schema_version': 1, 'phase': 3, 'date': day.isoformat(),
            'mode': 'DEMO_NOT_OPERATIONAL' if demo else 'operational_input',
            'snapshot_source': snapshot['source'], 'policy': policy,
            'recommendations': rows,
            'notes': [
                'Hanya rekomendasi; tidak mengubah database atau melakukan pemesanan.',
                'menus.stok sudah dikurangi reservasi. Tidak dikurangi lagi.',
                'Snapshot sebelum pelayanan; restok harus tiba sebelum pelayanan hari tersebut.',
                'Reservasi lintas hari/penjualan hari berjalan memerlukan penyesuaian terpisah; bukan demand sisa hari.',
                'Target = max(minimum_stok, ceil(point + kuantil residual bertanda)); restok = max(0,target-stok).',
                'minimum_stok adalah floor target, bukan stok cadangan yang ditambahkan ke permintaan.',
                'Coverage adalah uji kuantil demand sebelum melihat aktual hari uji, bukan evaluasi keuntungan/restok.',
                'Target layanan tidak dijamin tercapai; dependensi waktu dan stockout dapat membiaskan data penjualan.',
                'Tidak mengoptimalkan biaya, bahan baku, umur simpan, atau stok beberapa hari.',
                'Menu tanpa forecast/kalibrasi/snapshot memerlukan review manual; null bukan nol.',
            ]}


def report(result):
    lines = ['JSA FASE 3 - REKOMENDASI RESTOK',
             f"Mode: {result['mode']} | Tanggal: {result['date']}",
             'ID | MENU | STOK | TARGET | RESTOK | STATUS']
    for r in result['recommendations']:
        values = [r['menu_id'], r['nama_menu'], r['stok'], r['target_stock'], r['restock_qty'], r['status']]
        lines.append(' | '.join('-' if x is None else str(x) for x in values))
    return '\n'.join(lines + ['', *result['notes']])


def write_outputs(result, out):
    out = Path(out)
    out.mkdir(parents=True, exist_ok=True)
    (out / 'restock_result.json').write_text(json.dumps(result, indent=2, ensure_ascii=False, allow_nan=False), encoding='utf-8')
    (out / 'restock_report.txt').write_text(report(result) + '\n', encoding='utf-8')
    with (out / 'restock_menu.csv').open('w', newline='', encoding='utf-8-sig') as handle:
        fields = ['mode', 'date', 'menu_id', 'nama_menu', 'stok', 'minimum_stok', 'service_level',
                  'point', 'calibration_n', 'demand_target', 'target_stock', 'restock_qty', 'status',
                  'evaluation_n', 'evaluation_coverage']
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        for row in result['recommendations']:
            data = {k: v for k, v in row.items() if k != 'evaluation'}
            data.update(mode=result['mode'], date=result['date'],
                        evaluation_n=row['evaluation']['n'], evaluation_coverage=row['evaluation']['coverage'])
            writer.writerow(data)


def read_json(path):
    with Path(path).open(encoding='utf-8-sig') as handle:
        return json.load(handle)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--forecast', required=True, type=Path)
    parser.add_argument('--stock', required=True, type=Path)
    parser.add_argument('--policy', required=True, type=Path)
    parser.add_argument('--out', required=True, type=Path)
    parser.add_argument('--demo', action='store_true', help='Wajib untuk snapshot synthetic atau replay; bukan keputusan operasional.')
    args = parser.parse_args(argv)
    try:
        result = run_restock(read_json(args.forecast), read_json(args.stock), read_json(args.policy), demo=args.demo)
        write_outputs(result, args.out)
        print(report(result))
        return 0
    except (RestockError, OSError, ValueError, KeyError, TypeError, AttributeError, OverflowError) as exc:
        print(f'GALAT: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
