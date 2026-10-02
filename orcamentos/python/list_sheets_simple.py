import json
import sys
from pathlib import Path
import openpyxl


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "Arquivo não informado."}, ensure_ascii=False))
        return 1
    path = Path(sys.argv[1])
    if not path.is_file():
        print(json.dumps({"ok": False, "error": "Arquivo não encontrado."}, ensure_ascii=False))
        return 1
    try:
        wb = openpyxl.load_workbook(path, read_only=True, data_only=False, keep_links=False)
        sheets = [{"index": i, "name": name} for i, name in enumerate(wb.sheetnames)]
        wb.close()
        print(json.dumps({"ok": True, "sheets": sheets}, ensure_ascii=False))
        return 0
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc), "tipo": type(exc).__name__}, ensure_ascii=False))
        return 1

if __name__ == '__main__':
    raise SystemExit(main())
