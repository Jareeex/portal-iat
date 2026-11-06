"""
Konversi workbook 'CREW DATA & TRAINING RECORD' -> database/seeders/data/training.json
Pakai:  python database/seeders/convert_xlsx.py path/ke/file.xlsx
Format sumber: tiap crew = 3 baris (LAST CHECK / NEXT CHECK / PLAN CHECK), kolom = item training.
Sheet 'ALFA5 CREW (OLD)' sengaja diabaikan.
"""
import json, re, sys
from datetime import datetime, date
from openpyxl import load_workbook
from openpyxl.utils import column_index_from_string as CI

SRC = sys.argv[1] if len(sys.argv) > 1 else "crew.xlsx"
OUT = "database/seeders/data/training.json"

def T(col, section, name, validity=""):
    return dict(col=col, section=section, name=name, validity=validity)

def fw_block(cols, section, v):
    names = [
        ("Pilot Proficiency Check – Simulator PIC", v[0]), ("Pilot Proficiency Check – Simulator SIC", v[1]),
        ("Pilot Proficiency Check – Aircraft PIC", v[2]), ("Pilot Proficiency Check – Aircraft SIC", v[3]),
        ("Instrument Rating Check – Simulator", "12 Months"), ("Instrument Rating Check – Aircraft", "12 Months"),
        ("Line Check", "12 Months"), ("Technical Exam", "12 Months"), ("Night Current", "90 Days"),
        ("TCAS (Simulator)", "06/12 Months"), ("LOFT", ""),
    ]
    return [T(c, section, n, val) for c, (n, val) in zip(cols, names)]

def tail(cols, crm, dg, drill, ws, sec, eng, v_rvsm=True):
    n = [("Mandatory", "CRM", crm), ("Mandatory", "Dangerous Goods", dg), ("Mandatory", drill, "2 Yrs"),
         ("Mandatory", "Windshear", ws), ("Mandatory", "Aviation Security", sec), ("Mandatory", "English Proficiency", eng)]
    return [T(c, s, nm, v) for c, (s, nm, v) in zip(cols, n)]

def cols(a, b):
    from openpyxl.utils import get_column_letter as L
    return [L(i) for i in range(CI(a), CI(b) + 1)]

def common_tail(start, rw=False):
    """Mandatory(6)+Additional(5-7)+General(6) mulai kolom `start`."""
    from openpyxl.utils import get_column_letter as L
    i = CI(start)
    out = []
    mand = [("CRM", "1 Yr"), ("Dangerous Goods", "2 Yrs"), ("HUET" if rw else "Emergency Drill (CET)", "2 Yrs"),
            ("Windshear", "1 Yr"), ("Aviation Security", "1 Yr"), ("English Proficiency", "3 Yrs")]
    add = [("Basic Indoc.", ""), ] + ([] if rw else [("RVSM", "1 Yr"), ("PBN", "2 Yrs")]) + \
          [("ALAR / CFIT", "1 Yr"), ("SMS", "2 Yrs"), ("CCP or CC", ""), ("Basic Instructor", "")]
    gen = [("Sea & Jungle Survival", ""), ("HSE", ""), ("Ramp Safety", ""), ("TOEIC", ""), ("SAR", ""), ("Service Excellence", "")]
    for sec, lst in (("Mandatory", mand), ("Additional", add), ("General", gen)):
        for n, v in lst:
            out.append(T(L(i), sec, n, v)); i += 1
    return out

PIL = dict(name="C", dob="D", address="E", passport="F", nationality="G", joined="H", licence="I", rating="J", position="K")
SHEETS = {
  "FW PILOT": dict(group="fw_atr", marker="L", p=PIL,
    types=[T("M","Medical","Medical Examination","6 Months")] +
          fw_block(cols("N","X"),"ATR 42-500",["6 Months","12 Months","6 Months","12 Months"]) +
          fw_block(cols("Y","AI"),"EMB 135 BJ",["6 Months","12 Months","6 Months","12 Months"]) + common_tail("AJ")),
  "FW PILOT RSS": dict(group="fw_rss", marker="L", p=PIL,
    types=[T("M","Medical","Medical Examination","6 Months")] +
          fw_block(cols("N","X"),"EMB 135 BJ",["12 Months"]*4) + common_tail("Y")),
  "FW PILOT JAM": dict(group="fw_jam", marker="K",
    p=dict(name="C", dob="D", passport="E", nationality="F", joined="G", licence="H", rating="I", position="J"),
    types=[T("L","Medical","Medical Examination","6 Months")] +
          fw_block(cols("M","W"),"Hawker 900 XP",["6 Months","12 Months","6 Months","12 Months"]) + common_tail("X")),
  "RW PILOT": dict(group="rw", marker="L", p=PIL,
    types=[T("M","Medical","Medical Examination","6 Months")] +
          [T(c,"EC 155 B1",n,v) for c,n,v in zip(cols("N","X"),
            ["Pilot Proficiency Check – Simulator PIC","Pilot Proficiency Check – Simulator SIC","Pilot Proficiency Check – Aircraft PIC",
             "Pilot Proficiency Check – Aircraft SIC","Instrument Rating Check – Simulator","Instrument Rating Check – Aircraft",
             "Line Check","Technical Exam","Night Current","TCAS","LOFT"],
            ["12 Months","12 Months","12 Months","12 Months","12 Months","12 Months","12 Months","12 Months","90 Days","12 Months",""])] +
          common_tail("Y", rw=True)),
  "FA": dict(group="fa", marker="K", p=dict(name="C", dob="D", address="E", passport="F", joined="G", licence="H", rating="I", position="J"),
    types=[T("L","Medical","Medical Examination","12 Months"),
           T("M","ATR 42-500","Licence – Recurrent","12 Months"), T("N","ATR 42-500","Licence – Competency Check","12 Months"),
           T("O","Mandatory","CRM","1 Yr"), T("P","Mandatory","Dangerous Goods","2 Yrs"), T("Q","Mandatory","Emergency Drill (CET)","2 Yrs"),
           T("R","Mandatory","Aviation Security","1 Yr"), T("S","Additional","Basic Indoc.",""), T("T","Additional","SMS","2 Yrs"),
           T("U","Additional","Service Excellence",""), T("V","Additional","Training of Trainer","")]),
  "FOO & HLO": dict(group="foo_hlo", marker="M", p=dict(name="C", dob="D", address="E", passport="F", joined="G", licence="H", rating="K", position="L"),
    types=[T("I","Licence","HLO – Expiry","24 Months"), T("J","Licence","ACO – Expiry","24 Months"),
           T("N","Medical","Medical Examination","24 Months"),
           T("O","Licence","Recurrent","12 Months"), T("P","Licence","Competency Check","12 Months"), T("Q","Licence","Familiarization Flight","12 Months"),
           T("R","Mandatory","DRM","1 Yr"), T("S","Mandatory","Dangerous Goods","2 Yrs"), T("T","Mandatory","Aviation Security","1 Yr"),
           T("U","Additional","Basic Indoc.",""), T("V","Additional","SMS","24 Months"), T("W","Additional","COM Socialization",""),
           T("X","Additional","CCP or CC",""), T("Y","Additional","Basic Instructor",""),
           T("Z","General","Sea & Jungle Survival",""), T("AA","General","HSE",""), T("AB","General","Ramp Safety",""),
           T("AC","General","TOEIC",""), T("AD","General","SAR",""), T("AE","General","Service Excellence",""),
           T("AF","Specialist","HUET",""), T("AG","Specialist","Load Master",""), T("AH","Specialist","HLO","24 Months"),
           T("AI","SOP","F50",""), T("AJ","SOP","BE-1900D","")]),
}

def d(v):
    """-> 'YYYY-MM-DD' atau None"""
    if isinstance(v, datetime): return v.date().isoformat()
    if isinstance(v, date): return v.isoformat()
    if not isinstance(v, str): return None
    s = v.strip().lstrip("'").strip()
    for f in ("%d-%b-%y", "%d-%b-%Y", "%b-%y", "%b-%Y", "%Y-%m-%d"):
        try: return datetime.strptime(s, f).date().isoformat()
        except ValueError: pass
    return None

def s(v):
    if v is None: return None
    t = re.sub(r"\s+", " ", str(v)).strip()
    return None if t in ("", "-", "'-") else t

def parse_passport(v):
    t = s(v)
    if not t: return None, None
    m = re.search(r"(\d{1,2}-[A-Za-z]{3}-\d{2,4})\s*$", t)
    exp = d(m.group(1)) if m else None
    num = re.sub(r"[,\s]+$", "", t[:m.start()] if m else t).strip()
    return num or None, exp

wb = load_workbook(SRC, data_only=True)
out = dict(types={}, crews=[])
for sheet, cfg in SHEETS.items():
    ws = wb[sheet]; g = cfg["group"]; mk = CI(cfg["marker"]); p = {k: CI(v) for k, v in cfg["p"].items()}
    out["types"][g] = [dict(section=t["section"], name=t["name"], validity=t["validity"]) for t in cfg["types"]]
    rows = list(ws.iter_rows(values_only=True))
    cell = lambda r, c: rows[r][c - 1] if c - 1 < len(rows[r]) else None
    for r in range(len(rows)):
        if str(cell(r, mk) or "").strip().upper() != "LAST CHECK": continue
        blk = [r, r + 1, r + 2]
        pick = lambda key: next((cell(i, p[key]) for i in blk if key in p and s(cell(i, p[key]))), None) if key in p else None
        name = s(pick("name"))
        if not name: continue
        code = None
        m = re.search(r"\(([A-Za-z]{2,4})\)", name)
        if m: code = m.group(1).upper(); name = re.sub(r"\s*\([A-Za-z]{2,4}\)", "", name).strip()
        pn, pe = parse_passport(pick("passport"))
        rating = [s(cell(i, p["rating"])) for i in blk] if "rating" in p else []
        rec = []
        for idx, t in enumerate(cfg["types"]):
            c = CI(t["col"])
            last, nxt, plan = (d(cell(i, c)) for i in blk)
            if last or nxt or plan: rec.append([idx, last, nxt, plan])
        out["crews"].append(dict(
            group=g, name=name, code=code, dob=d(pick("dob")), address=s(pick("address")),
            passport_no=pn, passport_expiry=pe, nationality=s(pick("nationality")), joined_iat=d(pick("joined")),
            licence=s(pick("licence")), rating=" / ".join(x for x in rating if x) or None, position=s(pick("position")),
            records=rec))
json.dump(out, open(OUT, "w"), ensure_ascii=False, indent=1)
print(len(out["crews"]), "crew ->", OUT)
