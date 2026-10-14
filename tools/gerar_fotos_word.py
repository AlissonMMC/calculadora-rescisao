#!/usr/bin/env python3
"""Gera um DOCX com fotos padronizadas e converte para PDF.

Requer: python-docx, Pillow e LibreOffice instalado no computador que executa o sistema.
"""
from __future__ import annotations

import argparse
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path
from typing import Iterable, Iterator

try:
    from docx import Document
    from docx.enum.text import WD_ALIGN_PARAGRAPH
    from docx.shared import Cm, Pt
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError as exc:
    print(
        "Dependência Python ausente. Execute: py -3 -m pip install -r tools/requirements-fotos-word.txt",
        file=sys.stderr,
    )
    raise SystemExit(2) from exc


SUPPORTED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp", ".tif", ".tiff"}
MAX_PHOTOS = 800
MAX_IMAGE_BYTES = 40 * 1024 * 1024
MAX_TOTAL_BYTES = 1024 * 1024 * 1024
MAX_IMAGE_EDGE = 1800


def natural_key(value: str) -> list[object]:
    return [int(part) if part.isdigit() else part.casefold()
            for part in re.split(r"(\d+)", value)]


def locate_soffice() -> str | None:
    configured = os.environ.get("LIBREOFFICE_BIN", "").strip()
    if configured:
        candidate = Path(configured.strip('"'))
        if candidate.is_file():
            return str(candidate)
        found = shutil.which(configured)
        if found:
            return found

    for command in ("soffice.com", "soffice.exe", "soffice", "libreoffice"):
        found = shutil.which(command)
        if found:
            return found

    if os.name == "nt":
        candidates = [
            Path(r"C:\Program Files\LibreOffice\program\soffice.com"),
            Path(r"C:\Program Files\LibreOffice\program\soffice.exe"),
            Path(r"C:\Program Files (x86)\LibreOffice\program\soffice.com"),
            Path(r"C:\Program Files (x86)\LibreOffice\program\soffice.exe"),
        ]
        for candidate in candidates:
            if candidate.is_file():
                return str(candidate)
    else:
        for candidate in (Path("/usr/bin/soffice"), Path("/usr/bin/libreoffice")):
            if candidate.is_file():
                return str(candidate)
    return None


def image_sources(input_dir: Path | None, zip_path: Path | None) -> Iterator[tuple[str, bytes]]:
    """Valida nomes/tamanhos antes de processar e carrega uma imagem por vez."""
    if zip_path is not None:
        try:
            with zipfile.ZipFile(zip_path) as archive:
                entries = [
                    item for item in archive.infolist()
                    if not item.is_dir()
                    and Path(item.filename.replace("\\", "/")).suffix.casefold() in SUPPORTED_EXTENSIONS
                ]
                entries.sort(key=lambda item: natural_key(item.filename.replace("\\", "/").split("/")[-1]))
                if len(entries) > MAX_PHOTOS:
                    raise ValueError(f"O arquivo contém mais de {MAX_PHOTOS} imagens. Divida-o em dois arquivos.")

                total_bytes = 0
                for entry in entries:
                    if entry.file_size > MAX_IMAGE_BYTES:
                        raise ValueError(f"A imagem {Path(entry.filename).name} excede 40 MB.")
                    total_bytes += entry.file_size
                    if total_bytes > MAX_TOTAL_BYTES:
                        raise ValueError("O total das imagens excede 1 GB.")
                for entry in entries:
                    # Lê uma única imagem por vez e nunca extrai caminhos do ZIP no disco.
                    yield Path(entry.filename.replace("\\", "/")).name, archive.read(entry)
        except zipfile.BadZipFile as exc:
            raise ValueError("O arquivo enviado não é um ZIP válido.") from exc
    elif input_dir is not None:
        paths = [
            item for item in input_dir.iterdir()
            if item.is_file() and item.suffix.casefold() in SUPPORTED_EXTENSIONS
        ]
        paths.sort(key=lambda item: natural_key(item.name))
        if len(paths) > MAX_PHOTOS:
            raise ValueError(f"Selecione no máximo {MAX_PHOTOS} fotos por vez.")
        total_bytes = 0
        for path in paths:
            size = path.stat().st_size
            if size > MAX_IMAGE_BYTES:
                raise ValueError(f"A imagem {path.name} excede 40 MB.")
            total_bytes += size
            if total_bytes > MAX_TOTAL_BYTES:
                raise ValueError("O total das imagens excede 700 MB.")
        for path in paths:
            yield path.name, path.read_bytes()

def normalize_images(candidates: Iterable[tuple[str, bytes]], temp_dir: Path) -> list[Path]:
    normalized: list[Path] = []
    invalid: list[str] = []

    for filename, data in candidates:
        try:
            with Image.open(io.BytesIO(data)) as original:
                original.verify()
            with Image.open(io.BytesIO(data)) as original:
                image = ImageOps.exif_transpose(original)
                # 1800 px por lado mantém boa qualidade em 12 cm e reduz o peso
                # dos DOCX/PDF com centenas de fotos.
                image.thumbnail((MAX_IMAGE_EDGE, MAX_IMAGE_EDGE), Image.Resampling.LANCZOS)
                has_alpha = (
                    image.mode in ("RGBA", "LA")
                    or (image.mode == "P" and "transparency" in image.info)
                )
                if has_alpha:
                    image = image.convert("RGBA")
                    target = temp_dir / f"{len(normalized) + 1:04d}.png"
                    image.save(target, format="PNG", optimize=True)
                else:
                    image = image.convert("RGB")
                    target = temp_dir / f"{len(normalized) + 1:04d}.jpg"
                    image.save(target, format="JPEG", quality=88, optimize=True)
                normalized.append(target)
        except (UnidentifiedImageError, OSError, ValueError, Image.DecompressionBombError):
            invalid.append(filename)

    if not normalized:
        raise ValueError("Os arquivos enviados não contêm imagens válidas.")
    if invalid:
        print(f"Aviso: {len(invalid)} arquivo(s) inválido(s) ou corrompido(s) foram ignorados.", file=sys.stderr)
    return normalized


def build_docx(images: list[Path], output_path: Path, width_cm: float) -> None:
    document = Document()
    section = document.sections[0]
    section.page_width = Cm(21)
    section.page_height = Cm(29.7)
    section.top_margin = Cm(1.3)
    section.bottom_margin = Cm(1.3)
    section.left_margin = Cm(1.8)
    section.right_margin = Cm(1.8)

    normal = document.styles["Normal"]
    normal.font.name = "Arial"
    normal.font.size = Pt(10)
    normal.paragraph_format.space_after = Pt(0)

    # Duas imagens por página A4. O limite de altura deixa espaço para
    # espaçamento dos parágrafos e evita que uma foto empurre a seguinte.
    altura_maxima_cm = 11.2

    for index, image_path in enumerate(images):
        with Image.open(image_path) as original:
            imagem = original.copy()
            largura_px, altura_px = imagem.size

            # Fotos verticais que passariam do limite de altura são giradas
            # 90 graus para ficarem deitadas. A imagem original do usuário
            # nunca é modificada: esta é apenas uma cópia temporária.
            altura_se_largura_maxima = width_cm * altura_px / max(largura_px, 1)
            if altura_px > largura_px and altura_se_largura_maxima > altura_maxima_cm:
                imagem = imagem.transpose(Image.Transpose.ROTATE_270)
                rotacionada = image_path.with_name(image_path.stem + "_rotacionada" + image_path.suffix)
                imagem.save(rotacionada)
                image_path = rotacionada
                largura_px, altura_px = imagem.size

            # Ajusta dentro da caixa de  largura x altura sem distorcer a proporção.
            escala = min(width_cm / max(largura_px, 1), altura_maxima_cm / max(altura_px, 1))
            largura_final = largura_px * escala
            altura_final = altura_px * escala

        paragraph = document.add_paragraph()
        paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
        paragraph.paragraph_format.space_before = Pt(3)
        paragraph.paragraph_format.space_after = Pt(12)
        paragraph.paragraph_format.keep_together = True
        paragraph.add_run().add_picture(
            str(image_path),
            width=Cm(largura_final),
            height=Cm(altura_final),
        )

        # Força o próximo par a começar em uma página nova.
        if (index + 1) % 2 == 0 and index + 1 < len(images):
            document.add_page_break()

    document.save(output_path)


def convert_to_pdf(docx_path: Path, pdf_path: Path, output_dir: Path) -> None:
    soffice = locate_soffice()
    if not soffice:
        raise RuntimeError(
            "O LibreOffice não foi encontrado. Instale-o ou configure LIBREOFFICE_BIN apontando para soffice.com."
        )

    profile_dir = Path(tempfile.mkdtemp(prefix="fotos-word-lo-"))
    try:
        command = [
            soffice,
            "--headless",
            f"-env:UserInstallation={profile_dir.resolve().as_uri()}",
            "--convert-to", "pdf",
            "--outdir", str(output_dir),
            str(docx_path),
        ]
        result = subprocess.run(
            command,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            timeout=900,
            check=False,
        )
        if result.returncode != 0 or not pdf_path.is_file() or pdf_path.stat().st_size == 0:
            detail = (result.stderr or result.stdout or "").strip()
            raise RuntimeError("Não foi possível converter o Word para PDF." + (f" Detalhe: {detail[:500]}" if detail else ""))
    finally:
        shutil.rmtree(profile_dir, ignore_errors=True)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-dir", type=Path)
    parser.add_argument("--zip-file", type=Path)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--width", type=float, default=12.0)
    args = parser.parse_args()

    if (args.input_dir is None) == (args.zip_file is None):
        raise ValueError("Informe uma pasta de imagens ou um arquivo ZIP.")
    if not 5.0 <= args.width <= 17.4:
        raise ValueError("A largura deve estar entre 5 cm e 17,4 cm.")

    args.output_dir.mkdir(parents=True, exist_ok=True)
    docx_path = args.output_dir / "fotos_redimensionadas.docx"
    pdf_path = args.output_dir / "fotos_redimensionadas.pdf"

    candidates = image_sources(args.input_dir, args.zip_file)
    with tempfile.TemporaryDirectory(prefix="fotos-word-images-") as temp:
        normalized = normalize_images(candidates, Path(temp))
        build_docx(normalized, docx_path, args.width)
        convert_to_pdf(docx_path, pdf_path, args.output_dir)
        stats = {
            "ok": True,
            "photos": len(normalized),
            "width_cm": args.width,
            "docx": docx_path.name,
            "pdf": pdf_path.name,
        }
        print(json.dumps(stats, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        raise SystemExit(1)
