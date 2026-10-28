#!/usr/bin/env python3

"""
JSA Phase 4 - Operational Handoff & Audit Trail.

Read-only terhadap database/stok.

Tujuan:
- mengemas artifact Fase 2/3 menjadi satu immutable run;
- SHA-256 untuk input/output;
- manifest audit;
- atomic publication;
- latest.json hanya menunjuk run yang berhasil dikemas;
- mendeteksi artifact yang berubah/tampered.

Fase ini TIDAK:
- mengubah tabel menus;
- melakukan auto-restock;
- melakukan purchase order;
- mengubah output Fase 3.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import sys
import tempfile
import uuid

from datetime import datetime, timezone
from pathlib import Path
from typing import Any


SCHEMA_VERSION = 1

READY = "READY_FOR_REVIEW"
REVIEW = "REVIEW_REQUIRED"
BLOCKED = "BLOCKED"


class HandoffError(ValueError):
    pass


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()

    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)

    return digest.hexdigest()


def load_json(path: Path) -> Any:
    try:
        with path.open("r", encoding="utf-8") as handle:
            return json.load(handle)
    except (OSError, json.JSONDecodeError) as exc:
        raise HandoffError(
            f"JSON tidak dapat dibaca: {path}: {exc}"
        ) from exc


def artifact_record(path: Path) -> dict[str, Any]:
    if not path.is_file():
        raise HandoffError(f"Artifact tidak ditemukan: {path}")

    return {
        "name": path.name,
        "sha256": sha256_file(path),
        "size_bytes": path.stat().st_size,
    }


def walk_flags(value: Any) -> list[str]:
    """
    Cari sinyal yang secara konservatif membutuhkan review.

    Tidak bergantung pada satu schema Fase 3 tertentu.
    """
    flags: list[str] = []

    warning_values = {
        "review_required",
        "insufficient_data",
        "insufficient_calibration",
        "uncalibrated",
        "blocked",
        "invalid",
        "stale",
    }

    def visit(node: Any, path: str = "$") -> None:
        if isinstance(node, dict):
            for key, item in node.items():
                child_path = f"{path}.{key}"

                if isinstance(item, str):
                    lowered = item.strip().lower()

                    if lowered in warning_values:
                        flags.append(
                            f"{child_path}={item}"
                        )

                if (
                    key.lower() in {
                        "warnings",
                        "warning",
                        "errors",
                        "error",
                        "blocked_items",
                        "review_required",
                    }
                    and item
                ):
                    flags.append(
                        f"{child_path} memiliki nilai"
                    )

                visit(item, child_path)

        elif isinstance(node, list):
            for index, item in enumerate(node):
                visit(item, f"{path}[{index}]")

    visit(value)

    return sorted(set(flags))


def status_from_payloads(
    forecast_payload: Any,
    restock_payload: Any,
) -> tuple[str, list[str]]:
    flags = []

    flags.extend(walk_flags(forecast_payload))
    flags.extend(walk_flags(restock_payload))

    flags = sorted(set(flags))

    # Artifact valid secara teknis tetapi tetap perlu manusia
    # memutuskan apakah rekomendasi akan dijalankan.
    if flags:
        return REVIEW, flags

    return READY, []


def fingerprint(records: dict[str, dict[str, Any]]) -> str:
    payload = json.dumps(
        {
            key: records[key]["sha256"]
            for key in sorted(records)
        },
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")

    return hashlib.sha256(payload).hexdigest()


def safe_copy(source: Path, destination: Path) -> None:
    destination.parent.mkdir(
        parents=True,
        exist_ok=True,
    )

    shutil.copy2(source, destination)


def atomic_write_json(
    path: Path,
    payload: Any,
) -> None:
    path.parent.mkdir(
        parents=True,
        exist_ok=True,
    )

    temp_path = path.with_name(
        f".{path.name}.{uuid.uuid4().hex}.tmp"
    )

    try:
        with temp_path.open(
            "w",
            encoding="utf-8",
            newline="\n",
        ) as handle:
            json.dump(
                payload,
                handle,
                ensure_ascii=False,
                indent=2,
                sort_keys=True,
            )
            handle.write("\n")

        os.replace(temp_path, path)

    finally:
        if temp_path.exists():
            temp_path.unlink()


def build_handoff(
    *,
    forecast_path: Path,
    stock_path: Path,
    policy_path: Path,
    restock_path: Path,
    output_root: Path,
    csv_path: Path | None = None,
    report_path: Path | None = None,
    now: datetime | None = None,
) -> dict[str, Any]:

    required = {
        "forecast": forecast_path,
        "stock": stock_path,
        "policy": policy_path,
        "restock": restock_path,
    }

    optional = {}

    if csv_path is not None:
        optional["csv"] = csv_path

    if report_path is not None:
        optional["report"] = report_path

    # JSON wajib valid sebelum publikasi.
    forecast_payload = load_json(forecast_path)
    load_json(stock_path)
    load_json(policy_path)
    restock_payload = load_json(restock_path)

    records: dict[str, dict[str, Any]] = {}

    for name, path in {
        **required,
        **optional,
    }.items():
        records[name] = artifact_record(path)

    package_fingerprint = fingerprint(records)

    current = now or datetime.now(timezone.utc)

    if current.tzinfo is None:
        current = current.replace(tzinfo=timezone.utc)

    current = current.astimezone(timezone.utc)

    timestamp = current.strftime("%Y%m%dT%H%M%SZ")

    run_id = (
        f"{timestamp}-"
        f"{package_fingerprint[:12]}"
    )

    status, review_flags = status_from_payloads(
        forecast_payload,
        restock_payload,
    )

    output_root.mkdir(
        parents=True,
        exist_ok=True,
    )

    final_dir = output_root / run_id

    if final_dir.exists():
        raise HandoffError(
            f"Run ID sudah ada dan tidak boleh ditimpa: {run_id}"
        )

    temp_parent = output_root / ".tmp"
    temp_parent.mkdir(
        parents=True,
        exist_ok=True,
    )

    temp_dir = Path(
        tempfile.mkdtemp(
            prefix=f"{run_id}-",
            dir=temp_parent,
        )
    )

    manifest = {
        "schema_version": SCHEMA_VERSION,
        "phase": 4,
        "run_id": run_id,
        "created_at_utc": current.isoformat(),
        "status": status,
        "read_only": True,
        "auto_restock": False,
        "package_fingerprint": package_fingerprint,
        "review_flags": review_flags,
        "artifacts": records,
    }

    try:
        artifacts_dir = temp_dir / "artifacts"

        for name, source in {
            **required,
            **optional,
        }.items():

            suffix = source.suffix

            destination = (
                artifacts_dir
                / f"{name}{suffix}"
            )

            safe_copy(
                source,
                destination,
            )

        atomic_write_json(
            temp_dir / "manifest.json",
            manifest,
        )

        # Verifikasi hasil copy sebelum publish.
        for name, record in records.items():

            source = {
                **required,
                **optional,
            }[name]

            copied = (
                artifacts_dir
                / f"{name}{source.suffix}"
            )

            if (
                sha256_file(copied)
                != record["sha256"]
            ):
                raise HandoffError(
                    f"Hash berubah saat copy: {name}"
                )

        # Atomic rename: run parsial tidak pernah terlihat
        # sebagai completed run.
        os.replace(
            temp_dir,
            final_dir,
        )

    except Exception:
        shutil.rmtree(
            temp_dir,
            ignore_errors=True,
        )
        raise

    latest = {
        "schema_version": SCHEMA_VERSION,
        "run_id": run_id,
        "status": status,
        "manifest": (
            f"runs/{run_id}/manifest.json"
        ),
        "package_fingerprint": package_fingerprint,
    }

    # BLOCKED tidak boleh menggantikan last usable run.
    if status != BLOCKED:
        atomic_write_json(
            output_root.parent / "latest.json",
            latest,
        )

    return manifest


def verify_run(run_dir: Path) -> dict[str, Any]:
    manifest_path = run_dir / "manifest.json"

    manifest = load_json(manifest_path)

    if manifest.get("schema_version") != SCHEMA_VERSION:
        raise HandoffError(
            "Schema manifest tidak didukung."
        )

    artifacts = manifest.get("artifacts")

    if not isinstance(artifacts, dict):
        raise HandoffError(
            "Manifest tidak memiliki artifacts."
        )

    for name, record in artifacts.items():

        if not isinstance(record, dict):
            raise HandoffError(
                f"Record artifact invalid: {name}"
            )

        expected_hash = record.get("sha256")

        candidates = list(
            (run_dir / "artifacts").glob(
                f"{name}.*"
            )
        )

        if len(candidates) != 1:
            raise HandoffError(
                f"Artifact packaged tidak unik: {name}"
            )

        actual_hash = sha256_file(
            candidates[0]
        )

        if actual_hash != expected_hash:
            raise HandoffError(
                f"Integrity check gagal: {name}"
            )

    actual_fingerprint = fingerprint(
        artifacts
    )

    if (
        actual_fingerprint
        != manifest.get("package_fingerprint")
    ):
        raise HandoffError(
            "Package fingerprint tidak cocok."
        )

    return manifest


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser(
        description=(
            "JSA Phase 4 operational handoff "
            "and immutable audit package."
        )
    )

    result.add_argument(
        "--forecast",
        type=Path,
        required=True,
    )

    result.add_argument(
        "--stock",
        type=Path,
        required=True,
    )

    result.add_argument(
        "--policy",
        type=Path,
        required=True,
    )

    result.add_argument(
        "--restock",
        type=Path,
        required=True,
    )

    result.add_argument(
        "--csv",
        type=Path,
    )

    result.add_argument(
        "--report",
        type=Path,
    )

    result.add_argument(
        "--output-root",
        type=Path,
        default=Path(
            "storage/app/private/jsa/runs"
        ),
    )

    result.add_argument(
        "--verify-run",
        type=Path,
    )

    return result


def main(argv: list[str] | None = None) -> int:
    args = parser().parse_args(argv)

    try:
        if args.verify_run is not None:
            manifest = verify_run(
                args.verify_run
            )

        else:
            manifest = build_handoff(
                forecast_path=args.forecast,
                stock_path=args.stock,
                policy_path=args.policy,
                restock_path=args.restock,
                csv_path=args.csv,
                report_path=args.report,
                output_root=args.output_root,
            )

        print(
            json.dumps(
                manifest,
                ensure_ascii=False,
                indent=2,
                sort_keys=True,
            )
        )

        return 0

    except HandoffError as exc:
        print(
            json.dumps(
                {
                    "status": BLOCKED,
                    "error": str(exc),
                },
                ensure_ascii=False,
            ),
            file=sys.stderr,
        )

        return 1


if __name__ == "__main__":
    raise SystemExit(main())
