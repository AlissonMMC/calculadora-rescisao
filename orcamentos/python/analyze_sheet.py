import json, sys
from pathlib import Path
import openpyxl

sys.path.insert(0, str(Path(__file__).resolve().parent))
from analyze_all import build_meta


def main():
    if len(sys.argv) < 3:
        print(json.dumps({"ok": False, "error": "Uso: analyze_sheet.py arquivo.xlsx indice_da_aba"}, ensure_ascii=False))
        return 1
    path = Path(sys.argv[1])
    if not path.is_file():
        print(json.dumps({"ok": False, "error": "Arquivo não encontrado."}, ensure_ascii=False))
        return 1
    try:
        index = int(sys.argv[2])
    except (TypeError, ValueError):
        print(json.dumps({"ok": False, "error": "Índice da aba inválido."}, ensure_ascii=False))
        return 1
    try:
        wb = openpyxl.load_workbook(path, read_only=True, data_only=False, keep_links=False)
        if index < 0 or index >= len(wb.worksheets):
            print(json.dumps({"ok": False, "error": f"A aba de índice {index} não existe."}, ensure_ascii=False))
            wb.close()
            return 1
        ws = wb.worksheets[index]
        meta = build_meta(ws)
        wb.close()
        print(json.dumps({"ok": True, "sheet": meta}, ensure_ascii=False, allow_nan=False))
        return 0
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc), "tipo": type(exc).__name__}, ensure_ascii=False))
        return 1

if __name__ == '__main__':
    raise SystemExit(main())
