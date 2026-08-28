"""Snímek výsledkových listin z fo.sql — náhrada presenteru ResultsDatabase.

Reprodukuje app/presenters/ResultsDatabase.php doslova, včetně několika
míst, kde originál dělá něco překvapivého (viz komentáře).
"""
import sqlite3
from decimal import ROUND_HALF_UP, Decimal

KATEGORIE = {1: "A", 2: "B", 3: "C", 4: "D", 5: "E", 6: "F", 7: "G"}
KOLA = [("domaci", "Domácí kolo"), ("obvodni", "Obvodní kolo "),
        ("krajske", "Krajské kolo")]


def get_url_data(path):
    """Port getURLData() (ResultsDatabase.php:220) — hledá podřetězce v cestě."""
    heading, kolo, kategorie, rocnik = "", 0, 0, 0
    low = path.lower()
    for i, (urlname, expname) in enumerate(KOLA):
        if urlname in low:
            heading += expname + " "
            kolo = i + 1
    for i, kat in enumerate("abcdefg"):
        if "kategorie-" + kat in low:
            heading += "kategorie&nbsp;" + kat.upper() + ", "
            kategorie = i + 1
    for i in range(10, 100):
        if f"{i}-rocnik" in low:
            heading += f"{i}.&nbsp;ročník"
            rocnik = i
    return heading, rocnik, kolo, kategorie


def std_title(kolo):
    if kolo == 1:
        return ("<p>Pro postup do dalšího kola bylo třeba dosáhnout hodnocení "
                "alespoň 5&nbsp;bodů v&nbsp;alespoň pěti úlohách "
                "a&nbsp;odevzdat seriózně experimentální úlohu.</p>")
    if kolo == 3:
        # V originále chybí </p>; ponecháno kvůli shodě.
        return ("<p>K&nbsp;úspěchu v&nbsp;krajském kole je třeba mít "
                "alespoň 14&nbsp;bodů a&nbsp;dvě úlohy hodnocené "
                "alespoň 5&nbsp;body.")
    return ""


class Results:
    def __init__(self, db_path):
        self.db = sqlite3.connect(db_path)
        self.db.row_factory = sqlite3.Row

    def publish(self, rocnik, kolo, kategorie):
        r = self.db.execute(
            "SELECT publish_type_id, title_line FROM publish_res "
            "WHERE kraj_id=1 AND rocnik=? AND round=? AND category=?",
            (rocnik, kolo, KATEGORIE[kategorie])).fetchone()
        return (r["publish_type_id"], r["title_line"]) if r else (None, None)

    def rows(self, rocnik, kolo, kategorie):
        q = ["SELECT NULL poradi, name || ' ' || surname jmeno, class, teacher,"
             " school_show_name"]
        for i in range(1, 8):
            q.append(f", U{i}.points AS U{i}")
        q.append(", SUM(sub.points) AS 'sum', NULL AS 'mod', NULL status, adv, born_date")
        q.append(f"""
            FROM participants
            JOIN referents USING(referent_id)
            JOIN school USING(school_id)
            JOIN advance USING(participant_id)
            JOIN (SELECT * FROM submit WHERE prob_round={kolo}) AS sub USING(participant_id)
            LEFT JOIN (SELECT DISTINCT participant_id, '<strong>ano</strong>' adv
                       FROM advance WHERE round>{kolo}) AS adv USING(participant_id)""")
        for i in range(1, 8):
            q.append(f""" LEFT JOIN (SELECT participant_id, sub{i}.points
                             FROM submit AS sub{i}
                             WHERE prob_round={kolo} AND problem_id={i}) AS U{i}
                     USING(participant_id)""")
        q.append(f""" WHERE kraj_id=1 AND rocnik={rocnik}
              AND category='{KATEGORIE[kategorie]}' AND advance.round={kolo}
            GROUP BY participant_id
            ORDER BY SUM(sub.points) DESC, born_date ASC""")
        return [dict(r) for r in self.db.execute("".join(q))]

    # -- výpočty ---------------------------------------------------------
    @staticmethod
    def compute_mod(data, kolo):
        """mod = Σ U_i × (10 − průměr_i).

        Průměr se v originále dělí počtem VŠECH řádků, i těch s NULL body
        (ResultsDatabase.php:138-149) — ne počtem vyplněných."""
        for row in data:
            row["mod"] = 0.0
        for i in range(1, (7 if kolo == 1 else 4) + 1):
            total = sum((r[f"U{i}"] or 0) for r in data)
            cnt = len(data)
            if not cnt:
                continue
            for row in data:
                row["mod"] += (row[f"U{i}"] or 0) * (10 - total / cnt)

    @staticmethod
    def compute_status(data, kolo):
        if kolo <= 1:
            return
        for row in data:
            ok = sum(1 for i in range(1, 5) if (row[f"U{i}"] or 0) >= 5)
            if ok >= 2 and (row["sum"] or 0) >= 14:
                row["status"] = "úspěšný řešitel"

    @staticmethod
    def final_sort(data):
        """status, pak sum DESC, pak mod DESC — mod je tu ještě float.

        Python třídí stabilně; PHP 5 měl usort nestabilní.  Rozdíl se může
        projevit jen u řádků shodných ve všech třech klíčích."""
        return sorted(data, key=lambda r: (
            0 if (r.get("status") or "") else 1,
            -(r["sum"] or 0),
            -(r["mod"] or 0)))

    # -- vykreslení ------------------------------------------------------
    @staticmethod
    def title_line(rocnik, kolo, kategorie):
        out = "".join(f'<th style="padding:1em 2px;">{x}</th>'
                      for x in ("", "Jméno", "Třída", "Vyučující fyziky", "Škola"))
        for i in range(1, (7 if kolo == 1 else 4) + 1):
            out += f'<th style="width:2em;padding:2px;">Ú{i}</th>'
        out += '<th style="padding:2px">Celkem</th>'
        if rocnik >= 54:
            out += '<th style="padding:2px">Mod. body</th>'
        out += "<th></th>"
        if kolo == 1 or (kolo == 2 and kategorie == 5):
            out += '<th style="padding:2px">Postup</th>'
        return out

    def render(self, rocnik, kolo, kategorie):
        data = self.rows(rocnik, kolo, kategorie)
        if not data:
            return None
        self.compute_mod(data, kolo)
        self.compute_status(data, kolo)
        data = self.final_sort(data)
        for n, row in enumerate(data, start=1):
            row["poradi"] = f"{n}."

        pocet_uloh = 7 if kolo == 1 else 4
        radky = []
        for row in data:
            bunky = [row["poradi"], row["jmeno"], row["class"], row["teacher"],
                     row["school_show_name"]]
            hodnoty = [row[f"U{i}"] for i in range(1, pocet_uloh + 1)]
            soucet = row["sum"]
            # number_format zaokrouhluje od nuly, ne bankovně jako round()
            modstr = str(Decimal(row["mod"]).quantize(Decimal("0.01"),
                         rounding=ROUND_HALF_UP)).replace(".", ",")

            if rocnik == 61 and kolo == 1:
                # koronavirová záplata: body se nahradí ✓/✗, prázdné zůstanou
                modstr, soucet = "", ""
                hodnoty = ["" if v is None else ("✓" if v >= 5 else "✗")
                           for v in hodnoty]
            bunky += [_num(v) for v in hodnoty]
            bunky.append(_num(soucet))
            if rocnik >= 54:
                bunky.append(modstr)
            bunky.append(row.get("status") or "")
            if kolo == 1:
                bunky.append(row.get("adv") or "")
            radky.append('<tr style="padding:1em 2px;">'
                         + "".join(f'<td style="padding:.5em 2px;">{c}</td>' for c in bunky)
                         + "</tr>")
        hlavicka = self.title_line(rocnik, kolo, kategorie)
        sloupcu = hlavicka.count("<th")
        for r in radky:
            if r.count("<td") != sloupcu:
                raise AssertionError(
                    f"{rocnik}/{kolo}/{KATEGORIE[kategorie]}: "
                    f"{r.count('<td')} buněk vs {sloupcu} záhlaví")
        return ('<table class="vysledkovka vysledkovka-db">\n<thead>\n<tr>'
                + hlavicka + "</tr>\n</thead>\n<tbody>\n"
                + "\n".join(radky) + "\n</tbody>\n</table>")


def _num(v):
    """PHP tiskne celá čísla bez desetinné části; desetinná čárka je česká."""
    if v is None or v == "":
        return ""
    if isinstance(v, float) and v.is_integer():
        v = int(v)
    return str(v).replace(".", ",")
