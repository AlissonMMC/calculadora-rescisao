#!/usr/bin/env python3
"""Gera PDF de contestação de vistoria em A4."""
from __future__ import annotations
import argparse
import json
from pathlib import Path
from xml.sax.saxutils import escape
import tempfile

from pypdf import PdfReader, PdfWriter

from PIL import Image as PILImage
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    Image, KeepTogether, HRFlowable
)

ORANGE = colors.HexColor("#F97316")
DARK = colors.HexColor("#171717")
MUTED = colors.HexColor("#737373")
GREEN = colors.HexColor("#16804A")
EXIT = colors.HexColor("#C65A16")
BORDER = colors.HexColor("#DDDDDD")
PAPER_TEMPLATE = Path(__file__).resolve().parents[1] / "assets" / "images" / "papel-timbrado" / "papel-timbrado.pdf"


def paragraph_text(value: object) -> str:
    return escape(str(value or "")).replace("\r\n", "\n").replace("\n", "<br/>")


def load_photo(path: Path, max_width: float, max_height: float):
    with PILImage.open(path) as source:
        image = source.convert("RGB")
        image.thumbnail((int(max_width * 4), int(max_height * 4)))
        width_px, height_px = image.size
        image.save(path.with_suffix(".pdf.jpg"), "JPEG", quality=95, subsampling=0, optimize=True)
    target = path.with_suffix(".pdf.jpg")
    scale = min(max_width / max(width_px, 1), max_height / max(height_px, 1))
    return Image(str(target), width=width_px * scale, height=height_px * scale)


def photo_grid(paths: list[Path], max_height: float, width: float):
    if not paths:
        cell = Paragraph("Nenhuma foto adicionada", ParagraphStyle(
            "no_photo", fontName="Helvetica", fontSize=8, textColor=MUTED,
            alignment=TA_CENTER, backColor=colors.HexColor("#F7F7F7"),
            borderColor=BORDER, borderWidth=.5, borderPadding=8
        ))
        return Table([[cell]], colWidths=[width], style=TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (-1, -1), 3),
            ("RIGHTPADDING", (0, 0), (-1, -1), 3),
        ]))
    rows = []
    cell_width = (width - 8) / 2
    for index in range(0, len(paths), 2):
        row = []
        for path in paths[index:index + 2]:
            row.append(load_photo(path, cell_width, max_height))
        if len(row) == 1:
            row.append("")
        rows.append(row)
    return Table(rows, colWidths=[cell_width, cell_width], hAlign="LEFT", style=TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 2),
        ("RIGHTPADDING", (0, 0), (-1, -1), 2),
        ("TOPPADDING", (0, 0), (-1, -1), 2),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
    ]))


def build_pdf(data: dict, photos_dir: Path, output: Path, paper_template: Path | None = None):
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(
        name="DocTitle", parent=styles["Title"], fontName="Helvetica-Bold",
        fontSize=17, leading=20, textColor=DARK, alignment=TA_CENTER, spaceAfter=3
    ))
    styles.add(ParagraphStyle(
        name="DocSubtitle", parent=styles["Normal"], fontSize=7, leading=9,
        textColor=MUTED, alignment=TA_CENTER, spaceAfter=10
    ))
    styles.add(ParagraphStyle(
        name="SectionTitle", parent=styles["Heading2"], fontName="Helvetica-Bold",
        fontSize=10, leading=12, textColor=DARK, spaceBefore=0, spaceAfter=6
    ))
    styles.add(ParagraphStyle(
        name="FieldLabel", parent=styles["Normal"], fontSize=6.5,
        leading=8, textColor=MUTED, spaceAfter=2
    ))
    styles.add(ParagraphStyle(
        name="FieldValue", parent=styles["Normal"], fontName="Helvetica-Bold",
        fontSize=8.5, leading=11, textColor=DARK
    ))
    styles.add(ParagraphStyle(
        name="RoomTitle", parent=styles["Heading2"], fontName="Helvetica-Bold",
        fontSize=10.5, leading=13, textColor=DARK, spaceAfter=5
    ))
    styles.add(ParagraphStyle(
        name="EntryLabel", parent=styles["Normal"], fontName="Helvetica-Bold",
        fontSize=7, leading=9, textColor=GREEN, spaceAfter=4
    ))
    styles.add(ParagraphStyle(
        name="ExitLabel", parent=styles["Normal"], fontName="Helvetica-Bold",
        fontSize=7, leading=9, textColor=EXIT, spaceAfter=4
    ))
    styles.add(ParagraphStyle(
        name="Description", parent=styles["Normal"], fontSize=8,
        leading=11, textColor=DARK, wordWrap="CJK"
    ))
    styles.add(ParagraphStyle(
        name="DescriptionLabel", parent=styles["Normal"], fontName="Helvetica-Bold",
        fontSize=6.5, leading=8, textColor=MUTED, spaceAfter=3
    ))

    # Reserva o cabeçalho, o rodapé e as margens do papel timbrado A4.
    doc = SimpleDocTemplate(
        str(output), pagesize=A4,
        rightMargin=3.1 * cm, leftMargin=3.1 * cm,
        topMargin=3.6 * cm, bottomMargin=2.6 * cm,
        title="Contestação de Vistoria",
        author="Folha de Cálculo"
    )
    page_width = A4[0] - doc.leftMargin - doc.rightMargin
    story = [
        Paragraph("CONTESTAÇÃO DE VISTORIA", styles["DocTitle"]),
        Paragraph("COMPARATIVO ENTRE LAUDO DE ENTRADA E LAUDO DE SAÍDA", styles["DocSubtitle"]),
        HRFlowable(width="100%", thickness=1.5, color=ORANGE, spaceAfter=9),
    ]

    def field(label, value):
        return [Paragraph(escape(label.upper()), styles["FieldLabel"]),
                Paragraph(paragraph_text(value or "Não informado"), styles["FieldValue"])]

    details = [
        [field("Inquilino", data.get("nome_inquilino")), field("CPF", data.get("cpf_inquilino"))],
        [field("Número do contrato", data.get("numero_contrato")), field("Fiança", data.get("fianca"))],
        [field("Data da contestação", data.get("data_contestacao_br")), ""],
        [field("Endereço da locação", data.get("endereco_locacao")), ""],
    ]
    details_table = Table(details, colWidths=[page_width / 2, page_width / 2], hAlign="LEFT")
    details_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#FAFAFA")),
        ("BOX", (0, 0), (-1, -1), .6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), .35, BORDER),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("SPAN", (0, 3), (1, 3)),
    ]))
    story.extend([details_table, Spacer(1, 10)])

    photo_map = {}
    for photo in data.get("photos", []):
        key = (int(photo.get("room_index", 0)), str(photo.get("side", "")))
        photo_map.setdefault(key, []).append(photos_dir / str(photo.get("file", "")))

    col_width = page_width / 2
    rooms = data.get("comodos", [])
    for index, room in enumerate(rooms):
        entry_paths = photo_map.get((index, "entrada"), [])
        exit_paths = photo_map.get((index, "saida"), [])
        count = max(len(entry_paths), len(exit_paths))
        max_height = {0: 4.8, 1: 4.8, 2: 4.2, 3: 3.7, 4: 3.2, 5: 2.8, 6: 2.5, 7: 2.2, 8: 2.0, 9: 1.8, 10: 1.7}.get(count, 1.6) * cm
        title = Paragraph(f"{index + 1:02d} — {paragraph_text(room.get('nome') or 'Cômodo')}", styles["RoomTitle"])
        entry_cell = [Paragraph("LAUDO DE ENTRADA", styles["EntryLabel"]),
                      photo_grid(entry_paths, max_height, col_width - 5)]
        exit_cell = [Paragraph("LAUDO DE SAÍDA", styles["ExitLabel"]),
                     photo_grid(exit_paths, max_height, col_width - 5)]
        comparison = Table([[entry_cell, exit_cell]], colWidths=[col_width, col_width], hAlign="LEFT")
        comparison.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("BOX", (0, 0), (-1, -1), .5, BORDER),
            ("INNERGRID", (0, 0), (-1, -1), .35, BORDER),
            ("LEFTPADDING", (0, 0), (-1, -1), 6),
            ("RIGHTPADDING", (0, 0), (-1, -1), 6),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ]))
        description = [
            Paragraph("DESCRIÇÃO / JUSTIFICATIVA", styles["DescriptionLabel"]),
            Paragraph(paragraph_text(room.get("descricao") or "Sem descrição informada."), styles["Description"])
        ]
        description_box = Table([[description]], colWidths=[page_width])
        description_box.setStyle(TableStyle([
            ("BOX", (0, 0), (-1, -1), .5, BORDER),
            ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#FCFCFC")),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ]))
        block = [title, comparison, Spacer(1, 5), description_box, Spacer(1, 10)]
        story.append(KeepTogether(block))

    def footer(canvas, document):
        canvas.saveState()
        canvas.setFont("Helvetica", 6.5)
        canvas.setFillColor(MUTED)
        canvas.drawCentredString(A4[0] / 2, .65 * cm,
            f"Folha de Cálculo · Contestação #{data.get('id', '')} · Página {document.page}")
        canvas.restoreState()

    # Gera primeiro o conteúdo e, quando disponível, aplica o papel timbrado
    # como camada inferior para que a marca d'água fique atrás de textos e fotos.
    template = paper_template if paper_template is not None else PAPER_TEMPLATE
    template = Path(template) if template else None
    if template and template.is_file():
        with tempfile.NamedTemporaryFile(
            prefix="contestacao-conteudo-", suffix=".pdf",
            dir=str(output.parent), delete=False
        ) as temp_file:
            content_pdf = Path(temp_file.name)
        try:
            doc.filename = str(content_pdf)
            doc.build(story, onFirstPage=footer, onLaterPages=footer)

            source_reader = PdfReader(str(content_pdf))
            template_reader = PdfReader(str(template))
            if not template_reader.pages:
                raise ValueError("O PDF do papel timbrado não possui páginas.")
            background = template_reader.pages[0]
            writer = PdfWriter()
            for page in source_reader.pages:
                writer.add_page(page)
                # A página precisa estar vinculada ao PdfWriter antes da mesclagem (pypdf 7+).
                writer.pages[-1].merge_page(background, over=False)
            if source_reader.metadata:
                metadata = {
                    key: str(value) for key, value in source_reader.metadata.items()
                    if value is not None
                }
                if metadata:
                    writer.add_metadata(metadata)
            with tempfile.NamedTemporaryFile(
                prefix="contestacao-final-", suffix=".pdf",
                dir=str(output.parent), delete=False
            ) as merged_file:
                merged_pdf = Path(merged_file.name)
            try:
                with merged_pdf.open("wb") as final_file:
                    writer.write(final_file)
                merged_pdf.replace(output)
            finally:
                merged_pdf.unlink(missing_ok=True)
        finally:
            content_pdf.unlink(missing_ok=True)
    else:
        doc.filename = str(output)
        doc.build(story, onFirstPage=footer, onLaterPages=footer)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True, type=Path)
    parser.add_argument("--photos-dir", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    data = json.loads(args.input.read_text(encoding="utf-8"))
    build_pdf(data, args.photos_dir, args.output)
    print(json.dumps({"ok": True, "bytes": args.output.stat().st_size}, ensure_ascii=False))


if __name__ == "__main__":
    main()
