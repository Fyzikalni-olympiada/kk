#!/usr/bin/env python3
"""5. fáze migrace: 73 předeywovských stránek Karlových Varů (ročníky 45–56).

Zdroj ../fo/kvary/vysledky/ je hotové HTML tabulky ze systému, který
v Karlových Varech běžel před CMS.  Ročníky 45–55 v CouchDB vůbec nejsou,
takže tohle je jediný způsob, jak archiv doplnit.
"""
import html as htmllib
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT.parent / "fo" / "kvary" / "vysledky"
DST = ROOT / "content" / "kvary" / "vysledky"

KOLA = {"reg": ("krajske-kolo", "Krajské kolo"),
        "okr": ("okresni-kolo", "Okresní kolo")}

NAZVY = {"A": "Kategorie A", "B": "Kategorie B", "C": "Kategorie C",
         "D": "Kategorie D", "E": "Kategorie E", "EF": "Kategorie E a F",
         "G": "Archimediáda (kategorie G)"}
SLUGY = {"A": "kategorie-a", "B": "kategorie-b", "C": "kategorie-c",
         "D": "kategorie-d", "E": "kategorie-e", "EF": "kategorie-e-f",
         "G": "kategorie-g"}
PORADI = {"A": 1, "B": 2, "C": 3, "D": 4, "E": 5, "EF": 5, "G": 6}

NAZEV_SOUBORU = re.compile(r"^(\d{2})_([A-G]{1,2})_(reg|okr)\.html$")


def cti(path):
    raw = path.read_bytes()
    try:
        return raw.decode("utf-8"), None
    except UnicodeDecodeError:
        return raw.decode("cp1250"), f"cp1250: {path.name}"


def normalizuj_tabulky(text):
    """Sjednotí tabulky na class=\"vysledkovka\", aby legacy stránky
    vypadaly stejně jako tabulky převedené z XLS."""
    def sub(m):
        atributy = m.group(1)
        if "vysledkovka" in atributy:
            return '<table class="vysledkovka">'
        return '<table class="vysledkovka">'
    return re.sub(r"<table([^>]*)>", sub, text, flags=re.I)


def zpracuj(path):
    m = NAZEV_SOUBORU.match(path.name)
    if not m:
        return None
    rocnik, kat, kolo = int(m.group(1)), m.group(2), m.group(3)
    text, poznamka = cti(path)

    h2 = re.findall(r"<h2[^>]*>(.*?)</h2>", text, re.S | re.I)
    if len(h2) == 1:
        nadpis = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", h2[0])).strip()
        telo = re.sub(r"<h2[^>]*>.*?</h2>", "", text, count=1, flags=re.S | re.I)
    else:
        # Několik okresů na jedné stránce: nadpisy sekcí zůstávají v těle,
        # jen se sníží na h3, aby h1 stránky bylo jediné.
        kolo_nazev = "krajského" if kolo == "reg" else "okresního"
        nadpis = (f"Výsledky {kolo_nazev} kola {rocnik}.&nbsp;ročníku "
                  f"fyzikální olympiády {NAZVY[kat].lower()}")
        telo = re.sub(r"<(/?)h2([^>]*)>", r"<\g<1>h3\g<2>>", text, flags=re.I)

    telo = normalizuj_tabulky(telo).strip()
    return {
        "rocnik": rocnik, "kat": kat, "kolo": kolo,
        "cesta": DST / str(rocnik) / KOLA[kolo][0] / f"{SLUGY[kat]}.html",
        "nadpis": nadpis, "telo": telo, "poznamka": poznamka,
        "zdroj": f"kvary/vysledky/{path.name}",
    }


def hlavicka(pole):
    radky = ["---"]
    for k, v in pole.items():
        if v == "" or v is None:
            continue
        if isinstance(v, str) and (":" in v or v.startswith(("&", "'", '"'))):
            v = "'" + v.replace("'", "''") + "'"
        radky.append(f"{k}: {v}")
    radky.append("---")
    return "\n".join(radky) + "\n"


def main(write=False):
    if not SRC.is_dir():
        sys.exit(f"zdroj {SRC} neexistuje")
    polozky = [z for z in (zpracuj(p) for p in sorted(SRC.glob("*.html"))) if z]
    kolize, zapsano, poznamky = [], 0, []
    rocniky = {}

    for z in polozky:
        if z["poznamka"]:
            poznamky.append(z["poznamka"])
        if z["cesta"].exists():
            kolize.append(f"{z['zdroj']} -> {z['cesta'].relative_to(ROOT)} (obsah z CouchDB má přednost)")
            continue
        rocniky.setdefault(z["rocnik"], set()).add(z["kolo"])
        if write:
            z["cesta"].parent.mkdir(parents=True, exist_ok=True)
            z["cesta"].write_text(
                hlavicka({"type": "results", "title": NAZVY[z["kat"]],
                          "heading": z["nadpis"], "position": PORADI[z["kat"]],
                          "source": z["zdroj"]}) + z["telo"] + "\n",
                encoding="utf-8")
        zapsano += 1

    # rozcestníky ročníků a kol; position navazuje na existující schéma
    # (56 -> 100, 57 -> 99, …), tedy position = 156 - ročník
    sekce = 0
    for rocnik, kola in sorted(rocniky.items()):
        rok = DST / f"{rocnik}.html"
        if not rok.exists():
            if write:
                rok.write_text(hlavicka({
                    "type": "results-overview", "title": f"{rocnik}. ročník",
                    "position": 156 - rocnik,
                    "source": "kvary/vysledky/ (archiv před CMS)"}), encoding="utf-8")
            sekce += 1
        for kolo in sorted(kola):
            slug, nazev = KOLA[kolo]
            p = DST / str(rocnik) / f"{slug}.html"
            if not p.exists():
                if write:
                    p.parent.mkdir(parents=True, exist_ok=True)
                    p.write_text(hlavicka({
                        "type": "overview", "title": nazev,
                        "position": 1 if kolo == "reg" else 2,
                        "source": "kvary/vysledky/ (archiv před CMS)"}), encoding="utf-8")
                sekce += 1

    print(f"legacy souborů      : {len(polozky)}")
    print(f"stránek zapsáno     : {zapsano}")
    print(f"rozcestníků zapsáno : {sekce}")
    print(f"kolizí (přeskočeno) : {len(kolize)}")
    for k in kolize:
        print("   ", k)
    for p in poznamky:
        print("    kódování:", p)
    if not write:
        print("\n(zkušební běh, nic se nezapsalo — spusť s --write)")


if __name__ == "__main__":
    main(write="--write" in sys.argv)
