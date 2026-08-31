#!/usr/bin/env python3
"""Import výsledkových listin z doby před CMS.

Oba krajské weby měly před eywou vlastní statický archiv se stejnou
konvencí názvů `<ročník>_<kategorie>_<kolo>.html`:

  Karlovy Vary  ../fo/kvary/vysledky/            ročníky 45–56
  Praha         smazáno v ../fo commitem 3af6326 ročníky 45–55
                (rozbaleno do .recovered/praha-vysledky/)

V CouchDB tyhle ročníky vůbec nejsou a v fo.sql chybí body (tabulka
submit začíná až 56. ročníkem), takže tohle je jediný zdroj.
"""
import pathlib
import re
import shutil
import sys

import yaml

ROOT = pathlib.Path(__file__).resolve().parent.parent

WEBY = {
    "kvary": {
        "zdroj": ROOT.parent / "fo" / "kvary" / "vysledky",
        # Karlovy Vary mají v adrese holé číslo ročníku (56, 57, …)
        "rocnik": lambda n: str(n),
        "kola": {"reg": ("krajske-kolo", "Krajské kolo", "krajského", 1),
                 "okr": ("okresni-kolo", "Okresní kolo", "okresního", 2)},
        # navazuje na existující schéma 56 -> 100, 57 -> 99 …
        "position_rocnik": lambda n: 156 - n,
    },
    "praha": {
        "zdroj": ROOT / ".recovered" / "praha-vysledky",
        "rocnik": lambda n: f"{n}-rocnik",
        "kola": {"reg": ("krajske-kolo", "Krajské kolo", "krajského", 1),
                 "okr": ("obvodni-kolo", "Obvodní kolo", "obvodního", 2),
                 "dom": ("domaci-kolo", "Domácí kolo", "domácího", 3)},
        "position_rocnik": lambda n: 100 - n,
    },
}

# název | slug v URL | pořadí mezi sourozenci
KATEGORIE = {
    "A":      ("Kategorie A", "kategorie-a", 1),
    "B":      ("Kategorie B", "kategorie-b", 2),
    "BCD":    ("Kategorie B, C a D", "kategorie-b-c-d", 2),
    "C":      ("Kategorie C", "kategorie-c", 3),
    "D":      ("Kategorie D", "kategorie-d", 4),
    "E":      ("Kategorie E", "kategorie-e", 5),
    "EF":     ("Kategorie E a F", "kategorie-e-f", 5),
    "F":      ("Kategorie F", "kategorie-f", 6),
    "G":      ("Archimediáda (kategorie G)", "kategorie-g", 6),
    "postup": ("Postupující do krajského kola", "postupujici", 9),
}



def rozbor(jmeno):
    """`46_A_dom.html` -> (46, 'A', 'dom', 'html'); `46_E_postup.html` zvlášť."""
    m = re.match(r"^(\d{2})_E_postup\.html$", jmeno)
    if m:
        return int(m.group(1)), "postup", "okr", "html"
    m = re.match(r"^(\d{2})_([A-G]{1,3})_(reg|okr|dom)\.(html|pdf)$", jmeno)
    if m:
        return int(m.group(1)), m.group(2), m.group(3), m.group(4)
    return None


def kategorie_fraze(kat):
    """„Kategorie A“ -> „kategorie A“, ale „Archimediáda (kategorie G)“
    zůstává s velkým A — proto ne prosté .lower()."""
    n = KATEGORIE[kat][0]
    return n[0].lower() + n[1:] if n.startswith("Kategorie") else n


def cti(path):
    raw = path.read_bytes()
    try:
        return raw.decode("utf-8"), None
    except UnicodeDecodeError:
        return raw.decode("cp1250"), f"cp1250: {path.name}"


def normalizuj_tabulky(text):
    """Sjednotí tabulky na class="vysledkovka", ať legacy stránky vypadají
    stejně jako tabulky převedené z XLS."""
    return re.sub(r"<table[^>]*>", '<table class="vysledkovka">', text, flags=re.I)


def hlavicka(pole):
    """YAML hlavička; safe_dump kvůli uvozování — ruční heuristika neuměla
    hodnoty začínající -, *, %, @ ani víceřádkové."""
    cisto = {k: v for k, v in pole.items() if v not in ("", None)}
    telo = yaml.safe_dump(cisto, allow_unicode=True, sort_keys=False,
                          default_flow_style=False, width=10 ** 6)
    return "---\n" + telo + "---\n"


def zpracuj(web, cfg, path, r, zapis):
    if not r:
        return "neznámý název"
    rocnik, kat, kolo, pripona = r
    if kolo not in cfg["kola"]:
        return f"kolo '{kolo}' web {web} nezná"
    slug_kola, _nazev, gen_kola, _poradi = cfg["kola"][kolo]
    adresar = ROOT / "content" / web / "vysledky" / cfg["rocnik"](rocnik) / slug_kola
    cil = adresar / f"{KATEGORIE[kat][1]}.html"

    if pripona == "pdf":
        # Soubor ke stažení; stránku, která na něj odkáže, dělá .html varianta.
        # Bez ní by na disku ležel soubor, na který nikde nevede cesta.
        if not path.with_suffix(".html").exists():
            return "pdf bez stránky (nepublikuje se)"
        soubor = (ROOT / "files" / web / "vysledky" / cfg["rocnik"](rocnik)
                  / slug_kola / f"{KATEGORIE[kat][1]}.pdf")
        if zapis:
            soubor.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(path, soubor)
        return "pdf"

    if cil.exists():
        return "kolize"

    text, poznamka = cti(path)
    h2 = re.findall(r"<h2[^>]*>(.*?)</h2>", text, re.S | re.I)
    if len(h2) == 1:
        nadpis = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", h2[0])).strip()
        telo = re.sub(r"<h2[^>]*>.*?</h2>", "", text, count=1, flags=re.S | re.I)
    else:
        nadpis = (f"Výsledky {gen_kola} kola {rocnik}.&nbsp;ročníku "
                  f"fyzikální olympiády {kategorie_fraze(kat)}")
        telo = re.sub(r"<(/?)h2([^>]*)>", r"<\g<1>h3\g<2>>", text, flags=re.I)

    pole = {"type": "results", "title": KATEGORIE[kat][0], "heading": nadpis,
            "position": KATEGORIE[kat][2],
            "source": f"{web}/vysledky/{path.name} (archiv před CMS)"}
    pdf = path.with_suffix(".pdf")
    if pdf.exists():
        pole["soubor"] = f"/vysledky/{cfg['rocnik'](rocnik)}/{slug_kola}/{KATEGORIE[kat][1]}.pdf"
        pole["soubor_nazev"] = pdf.name

    if zapis:
        adresar.mkdir(parents=True, exist_ok=True)
        cil.write_text(hlavicka(pole) + normalizuj_tabulky(telo).strip() + "\n",
                       encoding="utf-8")
    return poznamka or "ok"


def sekce(web, cfg, rocniky, zapis):
    """Dogeneruje chybějící rozcestníky ročníků a kol (potřebují Karlovy Vary)."""
    n = 0
    for rocnik, kola in sorted(rocniky.items()):
        rok = ROOT / "content" / web / "vysledky" / f"{cfg['rocnik'](rocnik)}.html"
        if not rok.exists():
            if zapis:
                rok.parent.mkdir(parents=True, exist_ok=True)
                rok.write_text(hlavicka({
                    "type": "results-overview", "title": f"{rocnik}. ročník",
                    "position": cfg["position_rocnik"](rocnik),
                    "source": f"{web}/vysledky/ (archiv před CMS)"}), encoding="utf-8")
            n += 1
        for kolo in kola:
            slug, nazev, _gen, poradi = cfg["kola"][kolo]
            p = ROOT / "content" / web / "vysledky" / cfg["rocnik"](rocnik) / f"{slug}.html"
            if not p.exists():
                if zapis:
                    p.parent.mkdir(parents=True, exist_ok=True)
                    p.write_text(hlavicka({
                        "type": "overview", "title": nazev, "position": poradi,
                        "source": f"{web}/vysledky/ (archiv před CMS)"}), encoding="utf-8")
                n += 1
    return n


def main(zapis=False, jen=None):
    for web, cfg in WEBY.items():
        if jen and web != jen:
            continue
        zdroj = cfg["zdroj"]
        if not zdroj.is_dir():
            print(f"{web}: zdroj {zdroj} neexistuje, přeskakuji")
            continue
        vysledky = {}
        rocniky = {}
        for p in sorted(zdroj.iterdir()):
            if not p.is_file() or p.name.startswith("."):
                continue
            r = rozbor(p.name)
            stav = zpracuj(web, cfg, p, r, zapis)
            vysledky[stav] = vysledky.get(stav, 0) + 1
            if r and stav == "ok":
                rocniky.setdefault(r[0], set()).add(r[2])
        n_sekci = sekce(web, cfg, rocniky, zapis)
        print(f"{web}:")
        for k, v in sorted(vysledky.items()):
            print(f"    {k:24s} {v}")
        if n_sekci:
            print(f"    {'rozcestníků':24s} {n_sekci}")
    if not zapis:
        print("\n(zkušební běh, nic se nezapsalo — spusť s --write)")


if __name__ == "__main__":
    jen = next((a for a in sys.argv[1:] if not a.startswith("--")), None)
    main(zapis="--write" in sys.argv, jen=jen)
