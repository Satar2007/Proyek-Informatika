"""JSA Self-Learning Fase 1: dataset rolling yang read-only terhadap POS.

Hanya membaca CSV yang sudah diekspor oleh aplikasi. Tidak melakukan SQL,
training, mengubah model Ollama, atau mengubah stok/transaksi.
Python >= 3.11, standar library saja.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import os
import re
import secrets
import shutil
import sys
import tempfile
from collections import defaultdict
from contextlib import contextmanager
from datetime import date, datetime, timedelta, timezone
from pathlib import Path

SCHEMA_VERSION = 1
JAKARTA = timezone(timedelta(hours=7))
DATE_NAMES = ("tanggal", "date", "day", "transaction_date")
TOTAL_NAMES = ("total_porsi", "total_qty", "total_quantity", "jumlah_porsi", "porsi", "qty", "quantity", "jumlah", "total_terjual")
MENU_ID_NAMES = ("menu_id", "id_menu", "product_id")
MENU_NAME_NAMES = ("nama_menu", "menu_name", "nama", "product_name")
MENU_QTY_NAMES = ("porsi", "qty", "quantity", "jumlah", "jumlah_porsi", "total_qty", "total_porsi")


class DataError(ValueError):
    """Data tidak valid atau integritas hasil publikasi terganggu."""


def _sha_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as file:
        for chunk in iter(lambda: file.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _json_bytes(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode("utf-8")


def _normalize(name: str) -> str:
    return re.sub(r"[\s-]+", "_", name.lstrip("\ufeff").strip().lower())


def _field(headers: list[str], names: tuple[str, ...], label: str) -> str:
    normalized = {_normalize(h): h for h in headers}
    for candidate in names:
        if candidate in normalized:
            return normalized[candidate]
    raise DataError(f"Kolom {label} tidak ditemukan. Kolom tersedia: {', '.join(headers)}")


@contextmanager
def _open_csv(path: Path):
    """Streaming CSV: hanya jendela aktif yang ditahan di memori."""
    if not path.is_file():
        raise DataError(f"CSV tidak ditemukan: {path}")
    try:
        with path.open("r", encoding="utf-8-sig", newline="") as file:
            sample = file.read(4096)
            file.seek(0)
            try:
                dialect = csv.Sniffer().sniff(sample, delimiters=",;\t")
            except csv.Error:
                dialect = csv.excel
            reader = csv.DictReader(file, dialect=dialect)
            if not reader.fieldnames:
                raise DataError(f"CSV kosong atau tanpa header: {path.name}")
            headers = [str(k) for k in reader.fieldnames]
            yield headers, reader
    except UnicodeDecodeError as exc:
        raise DataError(f"{path.name}: gunakan encoding UTF-8") from exc


def _date(value: object, location: str) -> date:
    try:
        return date.fromisoformat(str(value).strip())
    except ValueError as exc:
        raise DataError(f"{location}: tanggal harus YYYY-MM-DD") from exc


def _integer(value: object, location: str, positive: bool = False) -> int:
    candidate = str(value).strip()
    if not candidate.isdecimal():
        raise DataError(f"{location}: harus integer {'positif' if positive else 'nonnegatif'}")
    number = int(candidate)
    if positive and number <= 0:
        raise DataError(f"{location}: harus integer positif")
    return number


def _csv_bytes(headers: list[str], rows: list[list[object]]) -> bytes:
    import io
    stream = io.StringIO(newline="")
    writer = csv.writer(stream, lineterminator="\n")
    writer.writerow(headers)
    writer.writerows(rows)
    return stream.getvalue().encode("utf-8")


def _prepare(total_path: Path, menu_path: Path, as_of: date, days: int) -> tuple[dict, bytes, bytes]:
    if days < 1 or days > 730:
        raise DataError("--days harus antara 1 dan 730")
    start = as_of - timedelta(days=days - 1)

    totals: dict[date, int] = {}
    future_rows = 0
    with _open_csv(total_path) as (total_headers, total_input):
        t_date = _field(total_headers, DATE_NAMES, "tanggal (total)")
        t_quantity = _field(total_headers, TOTAL_NAMES, "jumlah porsi (total)")
        for line, row in enumerate(total_input, start=2):
            if None in row:
                raise DataError(f"{total_path.name}:{line}: jumlah kolom tidak cocok")
            d = _date(row[t_date], f"{total_path.name}:{line}")
            if d > as_of:
                future_rows += 1
                continue
            if d < start:
                continue
            qty = _integer(row[t_quantity], f"{total_path.name}:{line} porsi")
            if d in totals:
                raise DataError(f"Duplikasi total untuk tanggal {d}")
            totals[d] = qty
    if not totals:
        raise DataError(f"Tidak ada data total pada periode {start} sampai {as_of}")

    menus: dict[tuple[date, int], tuple[str, int]] = {}
    sums: dict[date, int] = defaultdict(int)
    with _open_csv(menu_path) as (menu_headers, menu_input):
        m_date = _field(menu_headers, DATE_NAMES, "tanggal (menu)")
        m_id = _field(menu_headers, MENU_ID_NAMES, "menu_id")
        m_name = _field(menu_headers, MENU_NAME_NAMES, "nama_menu")
        m_qty = _field(menu_headers, MENU_QTY_NAMES, "jumlah porsi (menu)")
        for line, row in enumerate(menu_input, start=2):
            if None in row:
                raise DataError(f"{menu_path.name}:{line}: jumlah kolom tidak cocok")
            d = _date(row[m_date], f"{menu_path.name}:{line}")
            if d < start or d > as_of:
                continue
            menu_id = _integer(row[m_id], f"{menu_path.name}:{line} menu_id", positive=True)
            name = str(row[m_name]).strip()
            if not name:
                raise DataError(f"{menu_path.name}:{line}: nama_menu kosong")
            qty = _integer(row[m_qty], f"{menu_path.name}:{line} porsi")
            if d not in totals:
                raise DataError(f"Data menu tanggal {d} tidak memiliki pasangan di daily_total")
            key = (d, menu_id)
            if key in menus:
                raise DataError(f"Duplikasi menu_id={menu_id} untuk tanggal {d}")
            menus[key] = (name, qty)
            sums[d] += qty

    for d, qty in totals.items():
        if qty != sums[d]:
            raise DataError(f"Total porsi tanggal {d} tidak cocok: total={qty}, per-menu={sums[d]}")

    observed = sorted(totals)
    menu_keys = sorted(menus)
    total_rows = [[d.isoformat(), totals[d]] for d in observed]
    menu_rows = [[d.isoformat(), menu_id, menus[(d, menu_id)][0], menus[(d, menu_id)][1]]
                 for d, menu_id in menu_keys]

    missing_dates = []
    day = observed[0]
    while day <= observed[-1]:
        if day not in totals:
            missing_dates.append(day.isoformat())
        day += timedelta(days=1)
    lag = (as_of - observed[-1]).days
    readiness = len(observed) >= 60 and not missing_dates and lag <= 7

    total_bytes = _csv_bytes(["tanggal", "total_porsi"], total_rows)
    menu_bytes = _csv_bytes(["tanggal", "menu_id", "nama_menu", "porsi"], menu_rows)
    fingerprint = hashlib.sha256(
        _json_bytes({"schema": SCHEMA_VERSION, "as_of": as_of.isoformat(), "days": days,
                     "total_hash": hashlib.sha256(total_bytes).hexdigest(),
                     "menu_hash": hashlib.sha256(menu_bytes).hexdigest()})
    ).hexdigest()
    manifest = {
        "schema_version": SCHEMA_VERSION,
        "as_of": as_of.isoformat(),
        "window_days": days,
        "window_start": start.isoformat(),
        "fingerprint": fingerprint,
        "read_only_pos": True,
        "training_executed": False,
        "model_promoted": False,
        "totals": {"rows": len(observed), "quantity": sum(totals.values()),
                   "first_date": observed[0].isoformat(), "last_date": observed[-1].isoformat()},
        "menus": {"rows": len(menus), "unique_menu_ids": len({mid for _, mid in menu_keys})},
        "quality": {
            "missing_dates": missing_dates,
            "future_total_rows_excluded": future_rows,
            "lag_days": lag,
            "ready_for_training": readiness,
            "readiness_note": "Heuristik awal: >=60 tanggal, tanpa gap, dan data terbaru <=7 hari; bukan bukti model akurat",
            "source_label": "csv_unverified_origin",
        },
        "source": {
            "total_filename": total_path.name, "menu_filename": menu_path.name,
            "total_sha256": _sha_file(total_path), "menu_sha256": _sha_file(menu_path),
        },
        "artifacts": {
            "active_total.csv": hashlib.sha256(total_bytes).hexdigest(),
            "active_menu.csv": hashlib.sha256(menu_bytes).hexdigest(),
        },
    }
    return manifest, total_bytes, menu_bytes


def _verified_run(output_dir: Path, run_id: str) -> dict:
    if not re.fullmatch(r"[A-Za-z0-9_-]+", run_id):
        raise DataError("Run ID tidak valid")
    folder = output_dir / "runs" / run_id
    try:
        manifest = json.loads((folder / "manifest.json").read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise DataError("Manifest run tidak tersedia atau rusak") from exc
    if manifest.get("schema_version") != SCHEMA_VERSION or manifest.get("run_id") != run_id:
        raise DataError("Integritas manifest tidak cocok")
    for filename in ("active_total.csv", "active_menu.csv"):
        actual = folder / filename
        if not actual.is_file() or manifest.get("artifacts", {}).get(filename) != _sha_file(actual):
            raise DataError(f"Kegagalan integritas artifact: {filename}")
    return manifest


def verify_latest(output_dir: Path) -> dict:
    try:
        latest = json.loads((output_dir / "latest.json").read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise DataError("Pointer latest.json tidak ada atau rusak") from exc
    run_id = latest.get("run_id", "")
    if not isinstance(run_id, str):
        raise DataError("Run ID latest tidak valid")
    manifest = _verified_run(output_dir, run_id)
    if latest.get("fingerprint") != manifest.get("fingerprint"):
        raise DataError("Kegagalan integritas pointer latest.json")
    manifest_path = output_dir / "runs" / run_id / "manifest.json"
    if latest.get("manifest_sha256") != _sha_file(manifest_path):
        raise DataError("Kegagalan integritas manifest latest.json")
    return {"valid": True, "run_id": run_id, "manifest": manifest}


def _prune_old_runs(output_dir: Path, current_run_id: str, keep: int) -> int:
    """Hapus hanya hasil dataset yang dibuat modul ini, bukan data asli."""
    if keep < 2 or keep > 20:
        raise DataError("--keep harus 2 sampai 20 versi")
    runs = output_dir / "runs"
    recognized: list[Path] = []
    for folder in runs.iterdir():
        if folder.is_symlink() or not folder.is_dir():
            continue
        if not re.fullmatch(r"[0-9]{8}T[0-9]{12}Z_[0-9a-f]{8}", folder.name):
            continue
        try:
            manifest = json.loads((folder / "manifest.json").read_text(encoding="utf-8"))
            if manifest.get("schema_version") != SCHEMA_VERSION or manifest.get("run_id") != folder.name:
                continue
        except (OSError, json.JSONDecodeError):
            continue
        recognized.append(folder)
    ordered = sorted(recognized, key=lambda x: x.name, reverse=True)
    protected_names = {current_run_id} | {folder.name for folder in ordered[:keep]}
    count = 0
    for folder in ordered:
        if folder.name in protected_names:
            continue
        shutil.rmtree(folder)
        count += 1
    return count


def build_dataset(total_path: Path, menu_path: Path, output_dir: Path,
                  as_of: date, days: int = 365, dry_run: bool = False,
                  keep: int = 5) -> dict:
    if keep < 2 or keep > 20:
        raise DataError("--keep harus 2 sampai 20 versi")
    total_path, menu_path, output_dir = Path(total_path), Path(menu_path), Path(output_dir)
    manifest, total_bytes, menu_bytes = _prepare(total_path, menu_path, as_of, days)
    if dry_run:
        return {"status": "dry_run", "run_id": None, "manifest": manifest}

    output_dir.mkdir(parents=True, exist_ok=True)
    lock_path = output_dir / ".build.lock"
    try:
        handle = os.open(lock_path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    except FileExistsError as exc:
        raise DataError("Dataset Manager sedang berjalan (atau lock lama belum ditinjau)") from exc
    try:
        with os.fdopen(handle, "w", encoding="utf-8") as file:
            file.write(f"pid={os.getpid()}\n")
        latest_path = output_dir / "latest.json"
        if latest_path.exists():
            old = verify_latest(output_dir)  # jangan skip jika hasil sebelumnya rusak
            if old["manifest"].get("fingerprint") == manifest["fingerprint"]:
                return {"status": "unchanged", "run_id": old["run_id"], "manifest": old["manifest"]}

        runs_dir = output_dir / "runs"
        runs_dir.mkdir(exist_ok=True)
        run_id = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ") + "_" + secrets.token_hex(4)
        manifest["run_id"] = run_id
        manifest["created_at_utc"] = datetime.now(timezone.utc).isoformat()
        temp = Path(tempfile.mkdtemp(prefix=".tmp_jsa_", dir=str(runs_dir)))
        try:
            (temp / "active_total.csv").write_bytes(total_bytes)
            (temp / "active_menu.csv").write_bytes(menu_bytes)
            (temp / "manifest.json").write_bytes(_json_bytes(manifest))
            final = runs_dir / run_id
            temp.rename(final)
        finally:
            if temp.exists():
                shutil.rmtree(temp)
        pointer = {
            "run_id": run_id,
            "fingerprint": manifest["fingerprint"],
            "manifest_sha256": _sha_file(final / "manifest.json"),
        }
        pointer_tmp = output_dir / (".latest_" + secrets.token_hex(4) + ".tmp")
        try:
            pointer_tmp.write_bytes(_json_bytes(pointer))
            os.replace(pointer_tmp, latest_path)
        finally:
            if pointer_tmp.exists():
                pointer_tmp.unlink()
        # Snapshot lama adalah output buatan modul ini; file transaksi asli tidak disentuh.
        pruned = _prune_old_runs(output_dir, run_id, keep)
        return {"status": "created", "run_id": run_id, "pruned_runs": pruned, "manifest": manifest}
    finally:
        lock_path.unlink(missing_ok=True)


def yesterday_jakarta() -> date:
    return (datetime.now(JAKARTA) - timedelta(days=1)).date()


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="JSA Dataset Manager: dataset rolling read-only")
    parser.add_argument("--total", type=Path, help="Path CSV total harian hasil ekspor JSA")
    parser.add_argument("--menu", type=Path, help="Path CSV menu harian hasil ekspor JSA")
    parser.add_argument("--out", type=Path, default=Path("storage/app/private/jsa/learning/datasets"))
    parser.add_argument("--days", type=int, default=365)
    parser.add_argument("--keep", type=int, default=5, help="Jumlah versi dataset yang dipertahankan (2-20)")
    parser.add_argument("--as-of", type=str, default=None, help="Tanggal akhir, YYYY-MM-DD; default kemarin WIB")
    parser.add_argument("--dry-run", action="store_true", help="Validasi tanpa membuat file hasil")
    parser.add_argument("--verify", action="store_true", help="Verifikasi integritas dataset terbaru")
    args = parser.parse_args(argv)
    try:
        if args.verify:
            output = verify_latest(args.out)
        else:
            if args.total is None or args.menu is None:
                parser.error("--total dan --menu wajib, kecuali menggunakan --verify")
            cutoff = _date(args.as_of, "--as-of") if args.as_of else yesterday_jakarta()
            output = build_dataset(args.total, args.menu, args.out, cutoff, args.days, args.dry_run, args.keep)
        print(json.dumps(output, indent=2, ensure_ascii=False, default=str))
        return 0
    except (DataError, OSError) as exc:
        print(f"JSA Dataset Manager GAGAL: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
