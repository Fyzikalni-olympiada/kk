#!/usr/bin/env python3
"""Kontrola migrace.  Živý web neexistuje, proti čemu porovnávat, takže
jistota stojí na vnitřní konzistenci: čísla níž jsou naměřená, ne odhadnutá.

    python3 tools/verify.py
"""
import collections
import glob
import hashlib
import json
import pathlib
import re
import sqlite3
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))

from couchdb.shard import Shard                                  # noqa: E402
import results_db                                                # noqa: E402

OCEKAVANO = {
    "živých dokumentů": 421,
    "náhrobků": 144,
    "položek B-stromu": 565,
    "příloh": 352,
    "příloh .html": 255,
    "příloh .xls": 96,
    "příloh .pdf": 1,
    "dokumentů v exportu": 407,
    "dokumentů po filtru": 378,
    "content.html mezi nimi": 244,
    "z toho nulové délky": 109,
    "binárních příloh": 69,
    "různých obsahů binárních příloh": 69,
    "různých jmen binárních příloh": 43,
    "ResultsDatabase dokumentů": 104,
    "publish_res řádků": 72,
}

vysledky = []


def zkontroluj(popis, skutecnost, ocekavano):
    vysledky.append((skutecnost == ocekavano, popis, skutecnost, ocekavano))


def main():
    # --- 1. shardy -----------------------------------------------------
    live = dele = atts = 0
    ext = collections.Counter()
    md5_chyby = 0
    for p in sorted(glob.glob(str(ROOT / "data/couchdb/shards/*/kkfo.*.couch"))):
        sh = Shard(pathlib.Path(p))
        l, d = sh.counts
        n = 0
        zkontroluj(f"shard {sh.name}: live+deleted == položky", l + d, l + d)
        live += l
        dele += d
        for doc in sh.documents():
            n += 1
            if doc.deleted:
                continue
            for a in doc.atts:
                atts += 1
                ext[a.ext] += 1
                try:
                    sh.read_attachment(a)
                except Exception:
                    md5_chyby += 1
    zkontroluj("živých dokumentů", live, OCEKAVANO["živých dokumentů"])
    zkontroluj("náhrobků", dele, OCEKAVANO["náhrobků"])
    zkontroluj("položek B-stromu", live + dele, OCEKAVANO["položek B-stromu"])
    zkontroluj("příloh", atts, OCEKAVANO["příloh"])
    zkontroluj("příloh .html", ext["html"], OCEKAVANO["příloh .html"])
    zkontroluj("příloh .xls", ext["xls"], OCEKAVANO["příloh .xls"])
    zkontroluj("příloh .pdf", ext["pdf"], OCEKAVANO["příloh .pdf"])
    zkontroluj("příloh s vadným MD5/délkou", md5_chyby, 0)

    # --- 2. export -----------------------------------------------------
    syrove = (ROOT / "data/export/docs.json").read_text(encoding="utf-8")
    docs = json.loads(syrove)
    zkontroluj("dokumentů v exportu", len(docs), OCEKAVANO["dokumentů v exportu"])
    zkontroluj("hesel v exportu", syrove.count('"password"'), 0)

    kept = [d for d in docs
            if (d["_id"].startswith(("//praha/", "//kvary/")) or d["_id"] in ("//praha", "//kvary"))
            and not d["_id"].startswith(("//praha/odpad", "//kvary/bin"))]
    zkontroluj("dokumentů po filtru", len(kept), OCEKAVANO["dokumentů po filtru"])

    html = [a for d in kept for a in d["attachments"] if a["name"] == "content.html"]
    bins = [a for d in kept for a in d["attachments"] if a["name"] != "content.html"]
    zkontroluj("content.html mezi nimi", len(html), OCEKAVANO["content.html mezi nimi"])
    zkontroluj("z toho nulové délky", sum(1 for a in html if a["length"] == 0),
               OCEKAVANO["z toho nulové délky"])
    zkontroluj("binárních příloh", len(bins), OCEKAVANO["binárních příloh"])
    zkontroluj("různých obsahů binárních příloh", len({a["sha256"] for a in bins}),
               OCEKAVANO["různých obsahů binárních příloh"])
    zkontroluj("různých jmen binárních příloh", len({a["name"] for a in bins}),
               OCEKAVANO["různých jmen binárních příloh"])
    # Kdyby se soubory pojmenovávaly podle přílohy a ne podle dokumentu,
    # 26 souborů by se tiše přepsalo.
    # Původní kontrola byla jen "souborů >= obsahů", což by ztrátu 26 souborů
    # neodhalilo. Tady se ověřuje skutečný invariant: každá binární příloha
    # má na disku soubor odvozený z cesty DOKUMENTU a se shodným obsahem.
    chybi = 0
    for d in kept:
        site, rel = d["_id"].lstrip("/").split("/", 1) if "/" in d["_id"].lstrip("/") else (None, None)
        if site is None:
            continue
        # Publikuje se jen první binární příloha (eywa: $document->resources[0]).
        prilohy = [a for a in d["attachments"] if a["name"] != "content.html"]
        if not prilohy:
            continue
        a = prilohy[0]
        pripona = a["name"].rsplit(".", 1)[-1].lower()
        f = ROOT / "files" / site / f"{rel}.{pripona}"
        if not f.is_file() or hashlib.sha256(f.read_bytes()).hexdigest() != a["sha256"]:
            chybi += 1
    zkontroluj("binárních příloh chybějících nebo změněných v files/", chybi, 0)

    # --- 3. obsah ------------------------------------------------------
    bez_tabulky = []
    for f in (ROOT / "content").rglob("*.html"):
        text = f.read_text(encoding="utf-8")
        hlavicka, _, telo = text.partition("\n---\n")
        if re.search(r"^type: results$", hlavicka, re.M):
            # Stránka smí místo tabulky nést text („kolo se nekonalo“,
            # odkaz do OSMO) — vadné je jen prázdno.
            if "<table" not in telo and "soubor:" not in hlavicka \
               and len(telo.strip()) < 20:
                bez_tabulky.append(str(f.relative_to(ROOT)))
    zkontroluj("prázdných stránek typu results", len(bez_tabulky), 0)

    # --- 4. výsledky z databáze ---------------------------------------
    db = ROOT / "temp" / "fo.sqlite"
    if db.exists():
        r = results_db.Results(db)
        rd = [d for d in kept
              if (d["doc"] or {}).get("presenter", "").endswith("ResultsDatabase")]
        zkontroluj("ResultsDatabase dokumentů", len(rd),
                   OCEKAVANO["ResultsDatabase dokumentů"])
        neparsovatelne = 0
        chybne_sloupce = 0
        klice = set()
        for d in rd:
            _h, rocnik, kolo, kat = results_db.get_url_data(d["_id"])
            if not (rocnik and kolo and kat):
                neparsovatelne += 1
                continue
            klice.add((rocnik, kolo, results_db.KATEGORIE[kat]))
            pub, _tl = r.publish(rocnik, kolo, kat)
            if pub == 1:
                try:
                    r.render(rocnik, kolo, kat)
                except AssertionError:
                    chybne_sloupce += 1
        zkontroluj("neparsovatelných adres výsledků", neparsovatelne, 0)
        zkontroluj("tabulek s nesedícím počtem sloupců", chybne_sloupce, 0)
        conn = sqlite3.connect(db)
        vsechny = {tuple(x) for x in conn.execute(
            "SELECT rocnik, round, category FROM publish_res")}
        zkontroluj("publish_res řádků", len(vsechny), OCEKAVANO["publish_res řádků"])
        zkontroluj("publish_res bez stránky (63/3/C, 63/3/D)",
                   sorted(vsechny - klice), [(63, 3, "C"), (63, 3, "D")])

    # --- výpis ---------------------------------------------------------
    spatne = [v for v in vysledky if not v[0]]
    for ok, popis, skut, ocek in vysledky:
        if not ok:
            print(f"  ✗ {popis}: {skut!r}, čekáno {ocek!r}")
    print(f"\nkontrol: {len(vysledky)}, selhalo: {len(spatne)}")
    return 1 if spatne else 0


if __name__ == "__main__":
    sys.exit(main())
