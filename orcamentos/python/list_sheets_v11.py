from __future__ import annotations

import json
import re
import sys
import unicodedata
from pathlib import Path
from typing import Optional

import openpyxl


def norm(value: object) -> str:
    """Normaliza cabeçalhos para tolerar acentos, %, pontos e variações."""
    text = str(value or "").strip().upper()
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    text = re.sub(r"[^A-Z0-9]+", " ", text)
    return " ".join(text.split())


def to_number(value: object) -> Optional[float]:
    if value is None or value == "":
        return None
    if isinstance(value, bool):
        return float(value)
    try:
        n = float(value)
        return n if n == n and abs(n) != float("inf") else None
    except (TypeError, ValueError):
        return None


def cell_key(col: str, row: str) -> str:
    return f"{col.upper()}{row}"


def col_to_num(col: str) -> int:
    n = 0
    for ch in col.upper():
        n = n * 26 + (ord(ch) - 64)
    return n


def value_from_ref(ws, ref: str, cache: dict[str, Optional[float]]) -> Optional[float]:
    ref = ref.replace("$", "").upper()
    if ref in cache:
        return cache[ref]
    m = re.fullmatch(r"([A-Z]{1,3})(\d+)", ref)
    if not m:
        return None
    value = ws[f"{m.group(1)}{m.group(2)}"].value
    result = evaluate_value(ws, value, cache)
    cache[ref] = result
    return result


def expand_range(start_ref: str, end_ref: str):
    m1 = re.fullmatch(r"([A-Z]{1,3})(\d+)", start_ref.replace("$", "").upper())
    m2 = re.fullmatch(r"([A-Z]{1,3})(\d+)", end_ref.replace("$", "").upper())
    if not m1 or not m2:
        return []
    c1, r1 = m1.group(1), int(m1.group(2))
    c2, r2 = m2.group(1), int(m2.group(2))
    c1n, c2n = col_to_num(c1), col_to_num(c2)
    refs = []
    for row in range(min(r1, r2), max(r1, r2) + 1):
        for coln in range(min(c1n, c2n), max(c1n, c2n) + 1):
            n = coln
            s = ""
            while n:
                n, rem = divmod(n - 1, 26)
                s = chr(65 + rem) + s
            refs.append(f"{s}{row}")
    return refs


def evaluate_formula(ws, formula: str, cache: dict[str, Optional[float]]) -> Optional[float]:
    if not isinstance(formula, str) or not formula.strip().startswith("="):
        return to_number(formula)

    expr = formula.strip()[1:].replace("$", "")

    # SUM(A1:A10), SUM(A1,B2,C3), inclusive ranges.
    def sum_repl(match):
        inner = match.group(1)
        total = 0.0
        found = False
        for part in inner.split(","):
            part = part.strip()
            if ":" in part:
                a, b = [x.strip() for x in part.split(":", 1)]
                refs = expand_range(a, b)
            else:
                refs = [part]
            for ref in refs:
                value = value_from_ref(ws, ref, cache)
                if value is not None:
                    total += value
                    found = True
        return str(total if found else 0.0)

    expr = re.sub(r"SUM\(([^()]*)\)", sum_repl, expr, flags=re.IGNORECASE)

    # Replace any remaining cell references with their numeric values.
    def ref_repl(match):
        ref = match.group(0)
        value = value_from_ref(ws, ref, cache)
        return str(value if value is not None else 0.0)

    expr = re.sub(r"\b[A-Z]{1,3}\d+\b", ref_repl, expr, flags=re.IGNORECASE)

    # Only permit arithmetic, decimal points, whitespace and parentheses now.
    if not re.fullmatch(r"[0-9eE+\-*/().\s]+", expr):
        return None

    try:
        value = eval(expr, {"__builtins__": {}}, {})
        return to_number(value)
    except Exception:
        return None


def evaluate_value(ws, value: object, cache: dict[str, Optional[float]]) -> Optional[float]:
    if isinstance(value, str) and value.strip().startswith("="):
        return evaluate_formula(ws, value, cache)
    return to_number(value)


def find_text(ws, target: str, start_row: int = 1, end_row: Optional[int] = None):
    target_n = norm(target)
    end_row = end_row or ws.max_row
    for r in range(max(1, start_row), end_row + 1):
        for c in range(1, min(ws.max_column, 25) + 1):
            if norm(ws.cell(r, c).value) == target_n:
                return r, c
    return None, None


def detect_headers(ws):
    # Regras normalizadas para aceitar MATERIAIS/MO, MP/MO, MP%/MO%, M.O., etc.
    material_aliases = {"MATERIAIS", "MATERIAL", "MP", "MP PERC"}
    labor_aliases = {"MAO DE OBRA", "MO", "MO PERC"}

    candidates = []
    for r in range(1, min(ws.max_row, 25) + 1):
        vals = {c: norm(ws.cell(r, c).value) for c in range(1, min(ws.max_column, 20) + 1)}
        mats = [c for c, v in vals.items() if v in material_aliases]
        labs = [c for c, v in vals.items() if v in labor_aliases]
        totals = [c for c, v in vals.items() if v == "TOTAL"]
        if mats and labs:
            # Prioridade máxima para F/G, que são as colunas finais do orçamento.
            score = 100 if 6 in mats and 7 in labs else 0
            score += 20 if any(abs(m - l) == 1 for m in mats for l in labs) else 0
            candidates.append((score, r, mats, labs, totals))

    if candidates:
        _, header_row, mats, labs, totals = sorted(candidates, key=lambda x: (x[0], x[1]), reverse=True)[0]
        material_col = 6 if 6 in mats else mats[0]
        labor_col = 7 if 7 in labs else labs[0]
        material_header = str(ws.cell(header_row, material_col).value or "").strip()
        labor_header = str(ws.cell(header_row, labor_col).value or "").strip()
        total_col = max(totals) if totals else 9
        # Se a planilha tem MP/MO em D/E e MATERIAIS/MÃO DE OBRA em F/G, F/G vencem.
        if 6 in mats and 7 in labs:
            material_col, labor_col = 6, 7
        return {
            "header_row": header_row,
            "material_col": material_col,
            "labor_col": labor_col,
            "total_col": total_col,
            "material_header": material_header,
            "labor_header": labor_header,
            "recognized_material": material_header,
            "recognized_labor": labor_header,
        }

    # Fallback: procura independentemente, ainda priorizando F/G.
    material = labor = None
    material_header = labor_header = None
    header_row = None
    total_cols = []
    for r in range(1, min(ws.max_row, 25) + 1):
        for c in range(1, min(ws.max_column, 20) + 1):
            v = norm(ws.cell(r, c).value)
            if material is None and v in material_aliases:
                header_row, material = r, c
                material_header = str(ws.cell(r, c).value or "").strip()
            if labor is None and v in labor_aliases:
                header_row = header_row or r
                labor = c
                labor_header = str(ws.cell(r, c).value or "").strip()
            if v == "TOTAL":
                total_cols.append(c)
        if material and labor:
            break
    if material == 6 and labor == 7:
        pass
    elif ws.max_column >= 7:
        # Se F/G têm dados, eles são as colunas monetárias finais.
        data_rows = range(max(12, (header_row or 11) + 1), min(ws.max_row, 25) + 1)
        if any(ws.cell(r, 6).value not in (None, "") for r in data_rows) and any(ws.cell(r, 7).value not in (None, "") for r in data_rows):
            material, labor = 6, 7
            material_header = material_header or "MATERIAIS"
            labor_header = labor_header or "MÃO DE OBRA"
    return {
        "header_row": header_row,
        "material_col": material,
        "labor_col": labor,
        "total_col": max(total_cols) if total_cols else 9,
        "material_header": material_header,
        "labor_header": labor_header,
        "recognized_material": material_header,
        "recognized_labor": labor_header,
    }


def find_final_total_row(ws, header_row: Optional[int]):
    start = max(12, (header_row or 11) + 1)
    end = ws.max_row
    for r in range(start, end + 1):
        if norm(ws.cell(r, 1).value) == "TOTAL":
            return r
        for c in range(1, min(ws.max_column, 20) + 1):
            if norm(ws.cell(r, c).value) == "TOTAL":
                return r
    return None


def build_meta(ws):
    h = detect_headers(ws)
    total_row = find_final_total_row(ws, h.get("header_row"))
    cache: dict[str, Optional[float]] = {}

    items = []
    materials_sum = labor_sum = total_sum = 0.0
    found_mat = found_lab = found_total = False

    if total_row and h.get("material_col") and h.get("labor_col"):
        for r in range(12, total_row):
            a_raw = ws.cell(r, h["material_col"]).value
            b_raw = ws.cell(r, h["labor_col"]).value
            t_raw = ws.cell(r, h.get("total_col") or 9).value
            a = evaluate_value(ws, a_raw, cache)
            b = evaluate_value(ws, b_raw, cache)
            t = evaluate_value(ws, t_raw, cache)
            if a is not None:
                materials_sum += a
                found_mat = True
            if b is not None:
                labor_sum += b
                found_lab = True
            if t is not None:
                total_sum += t
                found_total = True
            if any(v not in (None, "") for v in (ws.cell(r, 1).value, a_raw, b_raw, t_raw)):
                description = str(ws.cell(r, 1).value or "").strip()
                items.append({
                    "row": r,
                    "service": description,
                    "materials": a,
                    "labor": b,
                    "total": t if t is not None else ((a or 0) + (b or 0) if (a is not None or b is not None) else None),
                })

    if not found_total:
        total_sum = materials_sum + labor_sum if found_mat or found_lab else 0.0

    return {
        "name": ws.title,
        "total_row": total_row,
        "material_cols": bool(h.get("material_col") and h.get("labor_col")),
        "material_col": h.get("material_col"),
        "labor_col": h.get("labor_col"),
        "total_col": h.get("total_col") or 9,
        "header_row": h.get("header_row"),
        "items": max(0, total_row - 12) if total_row else 0,
        "materials": materials_sum if found_mat else None,
        "labor": labor_sum if found_lab else None,
        "total": total_sum if found_total or found_mat or found_lab else None,
        "formula_warning": any(
            isinstance(ws.cell(r, c).value, str) and ws.cell(r, c).value.strip().startswith("=")
            for r in range(12, min(total_row or 12, 80))
            for c in range(1, min(ws.max_column, 10) + 1)
        ),
        "material_header": h.get("recognized_material"),
        "labor_header": h.get("recognized_labor"),
        "items_preview": items,
    }


def main() -> int:
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "Arquivo XLSX não informado."}, ensure_ascii=False))
        return 1
    path = Path(sys.argv[1])
    if not path.is_file():
        print(json.dumps({"ok": False, "error": "Arquivo não encontrado."}, ensure_ascii=False))
        return 1
    try:
        wb = openpyxl.load_workbook(path, read_only=False, data_only=False)
        sheets = [build_meta(ws) for ws in wb.worksheets]
        wb.close()
        print(json.dumps({"ok": True, "sheets": sheets}, ensure_ascii=False, allow_nan=False))
        return 0
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc), "tipo": type(exc).__name__}, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
