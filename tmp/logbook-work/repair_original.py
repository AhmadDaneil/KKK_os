from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree as E
from copy import deepcopy

ROOT = Path(__file__).resolve().parents[2]
SRC = Path(r'C:/Users/user/Downloads/Log Book DIPLOMA BARUUU (1).docx')
OUT = ROOT / 'output/documents/Logbook_Asal_Ruang_Taip_Dibaiki.docx'
NS = {'w':'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
      'wps':'http://schemas.microsoft.com/office/word/2010/wordprocessingShape',
      'wpg':'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup',
      'a':'http://schemas.openxmlformats.org/drawingml/2006/main',
      'mc':'http://schemas.openxmlformats.org/markup-compatibility/2006'}
def el(tag, **attrs):
    prefix, name = tag.split(':')
    node = E.Element('{%s}%s' % (NS[prefix], name))
    for key, value in attrs.items():
        node.set('{%s}%s' % (NS[prefix], key) if prefix == 'w' else key, str(value))
    return node
def content(shape): return shape.find('wps:txbx/w:txbxContent', NS)
def words(node): return ''.join(node.xpath('.//w:t/text()', namespaces=NS)).strip()
def typing_paragraph():
    p = el('w:p'); pr = el('w:pPr')
    pr.append(el('w:spacing', before=0, after=0, line=240, lineRule='auto'))
    pr.append(el('w:ind', left=0, right=0, firstLine=0))
    pr.append(el('w:jc', val='left'))
    rpr = el('w:rPr'); rpr.append(el('w:rFonts', ascii='Arial', hAnsi='Arial', cs='Arial'))
    rpr.append(el('w:sz', val=22)); rpr.append(el('w:b', val=0)); rpr.append(el('w:color', val='000000'))
    pr.append(deepcopy(rpr)); p.append(pr)
    r = el('w:r'); r.append(rpr); t = el('w:t'); t.text=''; r.append(t); p.append(r)
    return p

with ZipFile(SRC) as z:
    root = E.fromstring(z.read('word/document.xml'))
    changed = 0
    for group in root.findall('.//wpg:wgp', NS):
        shapes = group.findall('wps:wsp', NS)
        labels = [s for s in shapes if content(s) is not None and 'TUGASAN YANG DIJALANKAN:' in words(s)]
        if len(labels) != 3: continue
        blanks = [s for s in shapes if content(s) is not None and not words(s)]
        if not blanks: continue
        labels.sort(key=lambda s: int(s.find('wps:spPr/a:xfrm/a:off',NS).get('y')))
        width = int(group.find('wpg:grpSpPr/a:xfrm/a:chExt', NS).get('cx'))
        height = int(group.find('wpg:grpSpPr/a:xfrm/a:chExt', NS).get('cy'))
        boundaries = []
        for s in shapes:
            x = s.find('wps:spPr/a:xfrm', NS)
            if x is not None and content(s) is None:
                extent = x.find('a:ext',NS); off = x.find('a:off',NS)
                if int(extent.get('cx')) > width * .9 and int(extent.get('cy')) < 20000:
                    boundaries.append(int(off.get('y')))
        boundaries = sorted(set(boundaries))
        assert len(boundaries) == 3
        first = deepcopy(blanks[0])
        for s in blanks: group.remove(s)
        first.find('wps:cNvPr',NS).set('name', 'Ruang taip perancangan')
        c = content(first)
        for child in list(c): c.remove(child)
        c.append(typing_paragraph())
        group.append(first)
        areas = [(first, 196628, boundaries[0] - 40000)]
        for i, s in enumerate(labels):
            y = int(s.find('wps:spPr/a:xfrm/a:off',NS).get('y'))
            end = boundaries[i+1] - 40000 if i+1 < len(boundaries) else height-30000
            areas.append((s,y,end))
            content(s).append(typing_paragraph())
            s.find('wps:cNvPr',NS).set('name', 'Ruang taip ' + ['pelaksanaan','hasil','kesimpulan'][i])
        for s, y, end in areas:
            x = s.find('wps:spPr/a:xfrm',NS)
            x.find('a:off',NS).set('y',str(y))
            x.find('a:ext',NS).set('cx',str(width-143256))
            x.find('a:ext',NS).set('cy',str(end-y))
            body = s.find('wps:bodyPr',NS)
            body.set('wrap','square'); body.set('vertOverflow','clip')
            body.set('horzOverflow','clip')
            props = s.find('wps:spPr',NS)
            if props.find('a:noFill',NS) is None:
                line = props.find('a:ln',NS)
                props.insert(list(props).index(line) if line is not None else len(props), el('a:noFill'))
        # Modern Word uses the editable DrawingML branch. Remove the obsolete
        # duplicate VML fallback for this repaired group only.
        alternate = group.xpath('ancestor::mc:AlternateContent[1]',namespaces=NS)[0]
        fallback = alternate.find('mc:Fallback',NS)
        if fallback is not None: alternate.remove(fallback)
        changed += 1
    assert changed == 23
    # Correct only the invisible typing paragraph under each existing week label:
    # retain its vertical dimensions but give typed text the full original width.
    body = root.find('w:body',NS)
    for i, node in enumerate(body):
        if node.tag == '{%s}p'%NS['w'] and words(node).startswith('MINGGU') and i+1<len(body):
            nxt = body[i+1]
            if nxt.tag != node.tag or words(nxt): continue
            ppr=nxt.find('w:pPr',NS)
            if ppr is None: ppr=el('w:pPr'); nxt.insert(0,ppr)
            ind=ppr.find('w:ind',NS)
            if ind is None: ind=el('w:ind'); ppr.append(ind)
            following=body[i+2] if i+2<len(body) else None
            if following is not None:
                original_indent=following.find('w:pPr/w:ind',NS)
                if original_indent is not None:
                    right=original_indent.get('{%s}right'%NS['w'])
                    if right is not None: ind.set('{%s}right'%NS['w'],right)
    OUT.parent.mkdir(parents=True,exist_ok=True)
    with ZipFile(OUT,'w',ZIP_DEFLATED) as out:
        for item in z.infolist():
            out.writestr(item,E.tostring(root,xml_declaration=True,encoding='UTF-8',standalone=True) if item.filename=='word/document.xml' else z.read(item.filename))
print(OUT)
print('Repaired groups:',changed,'; broad typing areas:',changed*4)
