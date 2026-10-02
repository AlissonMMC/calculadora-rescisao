from __future__ import annotations
import json, math, os, shutil, subprocess, sys
from copy import copy
from pathlib import Path
import openpyxl
from openpyxl import load_workbook
from openpyxl.drawing.image import Image as XLImage
from openpyxl.utils import get_column_letter
from openpyxl.styles import Alignment, Border

SHEETS=["Orçamento","Orçamento 2","Orçamento 3"]

def result(ok:bool,**kwargs): print(json.dumps({"ok":ok,**kwargs},ensure_ascii=False))
def num(v):
    if v is None or v=="": return None
    try:return float(v)
    except(TypeError,ValueError):return None

def plus10(v):
    n=num(v)
    if n is None:return v
    if n==0:return 0
    return n+10 if n%10==0 else math.ceil(n/10)*10

def plus20(v):
    n=num(v)
    if n is None:return v
    if n==0:return 0
    return n+20 if n%10==0 else math.ceil(n/10)*10+10

def find_exact(ws,text,max_row=None,max_col=None,start=1):
    target=text.strip().upper(); max_row=max_row or ws.max_row; max_col=min(max_col or ws.max_column,20)
    for row in ws.iter_rows(min_row=start,max_row=max_row,min_col=1,max_col=max_col):
        for cell in row:
            if str(cell.value or '').strip().upper()==target:return cell.row,cell.column
    return None,None

def _norm(v):
    s=str(v or '').strip().upper()
    repl=str.maketrans({'Á':'A','À':'A','Â':'A','Ã':'A','Ä':'A','É':'E','Ê':'E','Í':'I','Ó':'O','Ô':'O','Õ':'O','Ö':'O','Ú':'U','Ü':'U','Ç':'C'})
    return s.translate(repl)

def locate_structure(ws):
    pintura_row,_=find_exact(ws,'PINTURA INTERNA',start=1)
    header_row=None;mat_col=None;labor_col=None;total_col=None
    preferred_mat={'MATERIAIS','MATERIAL'}; preferred_labor={'MÃO DE OBRA','MAO DE OBRA'}
    alias_mat=preferred_mat|{'MP','MP%','MP %','M.P.','M.P'}; alias_labor=preferred_labor|{'MO','MO%','MO %','M.O.','M.O'}
    alias_mc=alias_lc=None
    for r in range(1,min(ws.max_row,25)+1):
        for c in range(1,min(ws.max_column,20)+1):
            v=_norm(ws.cell(r,c).value)
            if v in {_norm(x) for x in preferred_mat}: header_row=r;mat_col=c
            elif v in {_norm(x) for x in preferred_labor}: header_row=header_row or r;labor_col=c
            if v in {_norm(x) for x in alias_mat} and v not in {_norm(x) for x in preferred_mat}: alias_mc=c
            if v in {_norm(x) for x in alias_labor} and v not in {_norm(x) for x in preferred_labor}: alias_lc=c
        if mat_col and labor_col: break
    if mat_col is None: mat_col=alias_mc
    if labor_col is None: labor_col=alias_lc
    if header_row is None:
        for r in range(1,min(ws.max_row,25)+1):
            if any(_norm(ws.cell(r,c).value) in {_norm(x) for x in alias_mat|alias_labor} for c in range(1,min(ws.max_column,20)+1)):
                header_row=r;break
    # A origem costuma ter MP/MO em D/E e os valores finais em F/G.
    # Se F/G já possuem conteúdo, usa F/G como colunas finais do orçamento.
    if ws.max_column>=7 and (mat_col!=6 or labor_col!=7):
        f_has=any(ws.cell(r,6).value not in (None,'') for r in range(max(12,(header_row or 11)+1),min(ws.max_row,18)+1))
        g_has=any(ws.cell(r,7).value not in (None,'') for r in range(max(12,(header_row or 11)+1),min(ws.max_row,18)+1))
        if f_has and g_has: mat_col,labor_col=6,7
    total_row=None; search_end=(pintura_row-1) if pintura_row else ws.max_row
    for r in range(max(12,(header_row or 11)+1),search_end+1):
        for c in range(1,min(ws.max_column,20)+1):
            if _norm(ws.cell(r,c).value)=='TOTAL': total_row=r;break
        if total_row: break
    if total_col is None:
        # Prefer TOTAL column header on or after the labor column.
        hr=header_row or 11
        for c in range((labor_col or 7),min(ws.max_column,20)+1):
            if _norm(ws.cell(hr,c).value)=='TOTAL': total_col=c
        if total_col is None: total_col=9
    return {'total_row':total_row,'pintura_row':pintura_row,'header_row':header_row,'material_col':mat_col,'labor_col':labor_col,'total_col':total_col,'material_cols':bool(mat_col and labor_col)}

def configure(ws,cfg,provider,structure):
    nome=str(provider.get('nome',''));cpf=str(provider.get('cpf',''))
    ws['A1']=ws['A1'].value if ws['A1'].value is not None else ''
    ws['A2']=f'Prestador de Serviços: {nome}'
    ws['A3']=f'CPF: {cpf}'
    ws['A4']='IMOBILIARIA JAU - ORÇAMENTO'
    # Preserve the template's merged title area; locate it dynamically.
    title_range=None
    for rng in list(ws.merged_cells.ranges):
        if rng.min_row<=2 and rng.max_row>=9 and rng.min_col<=9 and rng.max_col>=4:
            title_range=rng;break
    if title_range:
        ws.cell(title_range.min_row,title_range.min_col).value='Orçamento'
    else:
        ws['D2']='Orçamento'
        try:ws.merge_cells('D2:I9')
        except Exception:pass
    hr=structure.get('header_row') or 11;mc=structure.get('material_col') or 6;lc=structure.get('labor_col') or 7;tc=structure.get('total_col') or 9
    ws.cell(hr,mc).value='MATERIAIS';ws.cell(hr,lc).value='MÃO DE OBRA';ws.cell(hr,tc).value='TOTAL'
    for c in (ws.cell(hr,mc),ws.cell(hr,lc),ws.cell(hr,tc)):
        c.alignment=copy(Alignment(horizontal='center',vertical='center',wrap_text=False))
    ws['A5']=f"Locatário: {cfg['locatario']}";ws['A6']=f"Endereço: {cfg['endereco']}";ws['A7']=f"Contato: {cfg['contato']}";ws['A8']=f"Data: {cfg['data']}";ws['A9']=f"Metragem: {cfg['metragem']:g} m²"
    ws.column_dimensions[get_column_letter(mc)].width=max(16.5,float(ws.column_dimensions[get_column_letter(mc)].width or 8.43))
    ws.column_dimensions[get_column_letter(lc)].width=30.0
    fnt=copy(ws.cell(hr,lc).font); fnt.sz=10; ws.cell(hr,lc).font=fnt
    if tc>=1:ws.column_dimensions[get_column_letter(tc)].width=max(14,float(ws.column_dimensions[get_column_letter(tc)].width or 8.43))
    return hr,mc,lc,tc

def clean_bottom(ws,total_row):
    blank=Border()
    for rng in list(ws.merged_cells.ranges):
        if rng.min_row>total_row: ws.unmerge_cells(str(rng))
    for r in range(total_row+1,ws.max_row+1):
        ws.row_dimensions[r].hidden=True
        for c in range(1,ws.max_column+1):
            cell=ws.cell(r,c);cell.value=None;cell.border=copy(blank)
    for r in range(1,10):ws.row_dimensions[r].hidden=False
    # Explicitly remove visible border from the first row below the total.
    if total_row+1<=ws.max_row:
        for c in range(1,ws.max_column+1):ws.cell(total_row+1,c).border=copy(blank)

def create_signature_space(ws,total_row,settings):
    gap=max(1,min(6,int(settings.get('gap',1))));height=max(80,min(320,int(settings.get('height',150))))
    ws.insert_rows(total_row+1,amount=gap+1)
    spacer_start=total_row+1
    signature_row=total_row+1+gap
    for r in range(spacer_start,signature_row):
        ws.row_dimensions[r].height=20;ws.row_dimensions[r].hidden=False
    ws.row_dimensions[signature_row].height=max(95,int(height*0.75));ws.row_dimensions[signature_row].hidden=False
    blank=Border()
    for r in range(spacer_start,signature_row+1):
        for c in range(1,ws.max_column+1):
            cell=ws.cell(r,c);cell.border=copy(blank)
    return signature_row

def signature_anchor(settings):
    return {'left':'A','center':'C','right':'F'}.get(str(settings.get('align','left')),'A')

def insert_image(ws,path,anchor_row,settings):
    if not path:return
    p=Path(path)
    if not p.is_file():return
    try:
        img=XLImage(str(p)); target_h=max(80,min(320,int(settings.get('height',150))))
        orig_h=img.height or target_h;orig_w=img.width or target_h
        ratio=target_h/orig_h;img.height=target_h;img.width=max(1,int(orig_w*ratio));ws.add_image(img,f'{signature_anchor(settings)}{anchor_row}')
    except Exception:pass

def hide_aux(ws,structure,total_row):
    # Preserve current template behavior: hide auxiliary columns only.
    for col in (4,5,8):
        if col<=ws.max_column:ws.column_dimensions[get_column_letter(col)].hidden=True
    clean_bottom(ws,total_row)

def apply_total(ws,total_row,mc,lc,tc):
    last=total_row-1
    ws.cell(total_row,mc).value=f'=SUM({get_column_letter(mc)}12:{get_column_letter(mc)}{last})'
    ws.cell(total_row,lc).value=f'=SUM({get_column_letter(lc)}12:{get_column_letter(lc)}{last})'
    ws.cell(total_row,tc).value=f'=SUM({get_column_letter(mc)}{total_row}:{get_column_letter(lc)}{total_row})'

def find_soffice():
    for p in [r'C:\Program Files\LibreOffice\program\soffice.com',r'C:\Program Files\LibreOffice\program\soffice.exe',r'C:\Program Files (x86)\LibreOffice\program\soffice.com',r'C:\Program Files (x86)\LibreOffice\program\soffice.exe']:
        if os.path.isfile(p):return p
    found=shutil.which('soffice.com') or shutil.which('soffice') or shutil.which('libreoffice')
    if found:return found
    raise RuntimeError('LibreOffice não foi encontrado no servidor.')

def recalc_input(source,job):
    soffice=find_soffice();outdir=job/'_recalc';outdir.mkdir(exist_ok=True);profile=job/'_lo_recalc_profile'
    cmd=[soffice,'--headless',f'-env:UserInstallation={profile.as_uri()}','--convert-to','xlsx','--outdir',str(outdir),str(source)]
    proc=subprocess.run(cmd,capture_output=True,text=True,timeout=180)
    cand=outdir/source.name;shutil.rmtree(profile,ignore_errors=True)
    return cand if proc.returncode==0 and cand.is_file() else source

def make_workbook(cfg):
    source=Path(cfg['source']);job=Path(cfg['job_dir']);requested=cfg['sheet'];settings=cfg.get('signature_settings',{})
    providers=cfg.get('providers',[])
    wb=load_workbook(source,data_only=False)
    if requested not in wb.sheetnames:raise ValueError(f"A planilha '{requested}' não foi encontrada no arquivo.")
    src=wb[requested];structure=locate_structure(src)
    if not structure['total_row']:raise ValueError('Não foi possível localizar a linha TOTAL.')
    if not structure['pintura_row']:raise ValueError("Não foi possível localizar 'PINTURA INTERNA'.")
    if not structure['material_cols']:raise ValueError('Não foi possível localizar as colunas MATERIAIS e MÃO DE OBRA.')
    cache_path=recalc_input(source,job);cache_wb=load_workbook(cache_path,data_only=True);cache=cache_wb[requested] if requested in cache_wb.sheetnames else None
    copies=[wb.copy_worksheet(src),wb.copy_worksheet(src),wb.copy_worksheet(src)]
    for i,ws in enumerate(copies,1):ws.title=f'__ORC_TMP_{i}__'
    for original in list(wb.worksheets):wb.remove(original)
    for i,ws in enumerate(copies,1):
        s=locate_structure(ws);hr,mc,lc,tc=configure(ws,cfg,providers[i-1],s);ws.title=SHEETS[i-1]
        total=s['total_row'];pintura=s['pintura_row']
        if i in (2,3):
            for r in range(12,total):
                for col in (mc,lc):
                    base=cache.cell(r,col).value if cache is not None else ws.cell(r,col).value
                    ws.cell(r,col).value=plus10(base) if i==2 else plus20(base)
        apply_total(ws,total,mc,lc,tc)
        hide_aux(ws,s,total)
        sigrow=create_signature_space(ws,total,settings);insert_image(ws,cfg.get('images',{}).get(str(i)),sigrow,settings)
        # Remove accidental bottom border in surrounding signature area.
        for r in range(total+1,sigrow+1):
            for c in range(1,ws.max_column+1):
                ws.cell(r,c).border=copy(Border())
        ws.page_setup.fitToWidth=1;ws.page_setup.fitToHeight=0;ws.sheet_properties.pageSetUpPr.fitToPage=True;ws.print_options.horizontalCentered=False
    wb._sheets=copies
    out=job/'Planilha de Acionamento.xlsx';wb.calculation.fullCalcOnLoad=True;wb.calculation.forceFullCalc=True;wb.save(out);cache_wb.close();wb.close();return out

def export_pdf(xlsx,job,sheet,index):
    soffice=find_soffice();temp=job/f'_pdf_{index}.xlsx';profile=job/f'_lo_profile_{index}';wb=load_workbook(xlsx,data_only=False)
    try:
        for ws in list(wb.worksheets):
            if ws.title!=sheet:wb.remove(ws)
        ws=wb[sheet];ws.page_setup.fitToWidth=1;ws.page_setup.fitToHeight=0;ws.sheet_properties.pageSetUpPr.fitToPage=True;wb.calculation.fullCalcOnLoad=True;wb.calculation.forceFullCalc=True;wb.save(temp)
    finally:wb.close()
    cmd=[soffice,'--headless',f'-env:UserInstallation={profile.as_uri()}','--convert-to','pdf','--outdir',str(job),str(temp)];proc=subprocess.run(cmd,capture_output=True,text=True,timeout=180);generated=job/f'{temp.stem}.pdf';target=job/f'{sheet}.pdf'
    if proc.returncode!=0 or not generated.is_file():raise RuntimeError((proc.stderr or proc.stdout or f'LibreOffice não criou {sheet}.pdf').strip())
    if target.exists(): target.unlink()
    generated.replace(target)
    temp.unlink(missing_ok=True)
    shutil.rmtree(profile,ignore_errors=True)

def main():
    try:
        cfg=json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'));xlsx=make_workbook(cfg);job=Path(cfg['job_dir'])
        for i,s in enumerate(SHEETS,1):export_pdf(xlsx,job,s,i)
        result(True,message='3 abas e 3 PDFs gerados com sucesso.')
    except Exception as exc:result(False,error=str(exc),tipo=type(exc).__name__)
if __name__=='__main__':
    if len(sys.argv)<2:result(False,error='Arquivo de configuração não informado.');sys.exit(1)
    main()
