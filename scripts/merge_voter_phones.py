#!/usr/bin/env python3
"""
Merge eligible voters (dues list) with staff phone numbers (GHIMS list).

Produces a CSV ready for EC voter upload: staff_id,name,phone

Usage:
  python scripts/merge_voter_phones.py \\
    --voters "path/to/dues.xlsx" \\
    --staff "path/to/ghims.xlsx" \\
    --out "path/to/eligible_voters_with_phones.csv"
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
from pathlib import Path

try:
    import openpyxl
except ImportError:
    print("Missing dependency: pip install openpyxl", file=sys.stderr)
    sys.exit(1)


def normalize_id(value) -> str | None:
    if value is None:
        return None
    if isinstance(value, float) and value.is_integer():
        value = int(value)
    text = str(value).strip()
    if re.fullmatch(r"\d+\.0", text):
        text = text[:-2]
    text = text.upper()
    return text or None


def normalize_phone(value) -> str | None:
    """Return local Ghana form 0XXXXXXXXX when possible."""
    if value is None:
        return None
    if isinstance(value, float) and value.is_integer():
        value = int(value)
    digits = re.sub(r"\D+", "", str(value).strip())
    if not digits:
        return None
    if digits.startswith("233") and len(digits) == 12:
        return "0" + digits[3:]
    if digits.startswith("0") and len(digits) == 10:
        return digits
    if len(digits) == 9:
        return "0" + digits
    return digits  # keep raw; importer / GhanaPhone will validate


def clean_name(value) -> str:
    if value is None:
        return ""
    return re.sub(r"\s+", " ", str(value).strip())


def find_header_index(headers: list, *candidates: str) -> int:
    lowered = [(h or "").strip().lower() for h in headers]
    for cand in candidates:
        cand_l = cand.lower()
        for i, h in enumerate(lowered):
            if h == cand_l:
                return i
    for cand in candidates:
        cand_l = cand.lower()
        for i, h in enumerate(lowered):
            if cand_l in h:
                return i
    raise KeyError(f"Could not find column among: {candidates}. Headers: {headers}")


def load_sheet_rows(path: Path) -> list[tuple]:
    wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
    ws = wb.active
    rows = [tuple(row) for row in ws.iter_rows(values_only=True)]
    wb.close()
    if not rows:
        raise ValueError(f"No rows in {path}")
    return rows


def load_staff(path: Path) -> dict[str, dict]:
    rows = load_sheet_rows(path)
    headers = [str(c).strip() if c is not None else "" for c in rows[0][:6]]
    id_i = find_header_index(headers, "Staff ID", "EMPLOYEE NO", "Employee No", "staff_id")
    name_i = find_header_index(headers, "Full Name", "NAME OF EMPLOYEE", "Name", "name")
    phone_i = find_header_index(headers, "Phone Number", "Phone", "phone", "Mobile")

    staff: dict[str, dict] = {}
    duplicates: list[str] = []
    for row in rows[1:]:
        if row is None or all(c is None or str(c).strip() == "" for c in row[:6]):
            continue
        sid = normalize_id(row[id_i] if id_i < len(row) else None)
        if not sid:
            continue
        phone = normalize_phone(row[phone_i] if phone_i < len(row) else None)
        name = clean_name(row[name_i] if name_i < len(row) else "")
        if sid in staff:
            duplicates.append(sid)
        staff[sid] = {"name": name, "phone": phone}

    if duplicates:
        print(f"Warning: duplicate staff IDs (last wins): {', '.join(duplicates[:10])}")
    return staff


def load_voters(path: Path) -> list[dict]:
    rows = load_sheet_rows(path)
    headers = [str(c).strip() if c is not None else "" for c in rows[0][:10]]
    id_i = find_header_index(headers, "EMPLOYEE NO", "Staff ID", "Employee No", "staff_id")
    name_i = find_header_index(headers, "NAME OF EMPLOYEE", "Full Name", "Name", "name")

    voters: list[dict] = []
    for row in rows[1:]:
        if row is None or all(c is None or str(c).strip() == "" for c in row[:5]):
            continue
        sid = normalize_id(row[id_i] if id_i < len(row) else None)
        if not sid:
            continue
        voters.append(
            {
                "staff_id": sid,
                "name": clean_name(row[name_i] if name_i < len(row) else ""),
            }
        )
    return voters


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Attach staff phone numbers to the eligible voters list."
    )
    parser.add_argument(
        "--voters",
        type=Path,
        required=True,
        help="Eligible voters Excel (dues / IPPD list with EMPLOYEE NO + name)",
    )
    parser.add_argument(
        "--staff",
        type=Path,
        required=True,
        help="Staff Excel with Staff ID + Phone Number (GHIMS)",
    )
    parser.add_argument(
        "--out",
        type=Path,
        default=Path("eligible_voters_with_phones.csv"),
        help="Output CSV path (default: eligible_voters_with_phones.csv)",
    )
    parser.add_argument(
        "--unmatched-out",
        type=Path,
        default=None,
        help="Optional CSV of voters with no phone match",
    )
    args = parser.parse_args()

    if not args.voters.is_file():
        print(f"Voters file not found: {args.voters}", file=sys.stderr)
        return 1
    if not args.staff.is_file():
        print(f"Staff file not found: {args.staff}", file=sys.stderr)
        return 1

    staff = load_staff(args.staff)
    voters = load_voters(args.voters)

    matched: list[dict] = []
    unmatched: list[dict] = []
    no_phone: list[dict] = []

    for voter in voters:
        sid = voter["staff_id"]
        hit = staff.get(sid)
        if not hit:
            unmatched.append(voter)
            continue
        phone = hit["phone"]
        if not phone:
            no_phone.append({**voter, "staff_name": hit["name"]})
            continue
        matched.append(
            {
                "staff_id": sid,
                # Prefer dues-list name (eligibility source of truth)
                "name": voter["name"] or hit["name"],
                "phone": phone,
            }
        )

    args.out.parent.mkdir(parents=True, exist_ok=True)
    with args.out.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(fh, fieldnames=["staff_id", "name", "phone"])
        writer.writeheader()
        writer.writerows(matched)

    unmatched_path = args.unmatched_out
    if unmatched_path is None and (unmatched or no_phone):
        unmatched_path = args.out.with_name(args.out.stem + "_unmatched.csv")

    if unmatched_path and (unmatched or no_phone):
        with unmatched_path.open("w", newline="", encoding="utf-8") as fh:
            writer = csv.DictWriter(
                fh, fieldnames=["staff_id", "name", "reason", "staff_name"]
            )
            writer.writeheader()
            for row in unmatched:
                writer.writerow(
                    {
                        "staff_id": row["staff_id"],
                        "name": row["name"],
                        "reason": "staff_id_not_in_staff_list",
                        "staff_name": "",
                    }
                )
            for row in no_phone:
                writer.writerow(
                    {
                        "staff_id": row["staff_id"],
                        "name": row["name"],
                        "reason": "matched_but_no_phone",
                        "staff_name": row.get("staff_name", ""),
                    }
                )

    print(f"Staff records loaded:     {len(staff)}")
    print(f"Eligible voters loaded:   {len(voters)}")
    print(f"Matched with phone:       {len(matched)}")
    print(f"No matching staff ID:     {len(unmatched)}")
    print(f"Matched but no phone:     {len(no_phone)}")
    print(f"Wrote upload CSV:         {args.out.resolve()}")
    if unmatched_path and (unmatched or no_phone):
        print(f"Wrote unmatched report:   {unmatched_path.resolve()}")

    return 0 if matched else 1


if __name__ == "__main__":
    raise SystemExit(main())
