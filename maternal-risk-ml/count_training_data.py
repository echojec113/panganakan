"""Print aggregate counts for the maternal risk training workbook.

This script deliberately avoids printing patient identifiers or row contents.
Run from any directory with: python maternal-risk-ml/count_training_data.py
"""

from math import ceil
from pathlib import Path
import sys

import pandas as pd


DEFAULT_WORKBOOK = Path(__file__).resolve().with_name("bjjj.xlsx")
TARGET_COLUMN = "Risk_Level"


def main() -> int:
    workbook = Path(sys.argv[1]).expanduser() if len(sys.argv) > 1 else DEFAULT_WORKBOOK

    if not workbook.is_file():
        print(f"Workbook not found: {workbook}", file=sys.stderr)
        return 1

    data = pd.read_excel(workbook)
    row_count = len(data)
    test_count = ceil(row_count * 0.20)
    train_count = row_count - test_count

    print(f"Workbook: {workbook.name}")
    print(f"Rows loaded (excluding fully blank spreadsheet rows): {row_count}")
    print(f"Current train_test_split estimate (80/20): {train_count} train / {test_count} test")

    if TARGET_COLUMN not in data.columns:
        print(f"Target column '{TARGET_COLUMN}' is missing; class counts unavailable.")
        return 0

    labels = data[TARGET_COLUMN].astype("string").str.strip().str.upper()
    recognized = labels.isin(["LOW", "HIGH"])
    print(f"Rows with LOW/HIGH target labels: {int(recognized.sum())}")
    print(f"Rows with missing or unrecognized target labels: {int((~recognized).sum())}")
    print("Target counts:")
    for label in ("LOW", "HIGH"):
        print(f"  {label}: {int((labels == label).sum())}")

    missing_feature_cells = data.drop(columns=[TARGET_COLUMN]).isna().sum().sum()
    print(f"Missing feature cells (excluding target): {int(missing_feature_cells)}")
    print("Note: these counts describe the workbook, not proof of which data trained the saved .pkl model.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
