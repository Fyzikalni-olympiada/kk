#!/usr/bin/env python3
"""Postaví temp/fo.sqlite z ../fo/fo.sql.

MySQL ani jeho klient tu nejsou a PHP nemá mysqli/pdo_mysql/pdo_sqlite,
takže se DDL nepřekládá — pro sedm tabulek, kterých se presenter dotýká,
je ručně napsané minimální schéma a pohled participants podle definice
z dumpu.
"""
import pathlib
import sqlite3
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import fosql                                                    # noqa: E402

ROOT = pathlib.Path(__file__).resolve().parent.parent
FO_SQL = ROOT.parent / "fo" / "fo.sql"
OUT = ROOT / "temp" / "fo.sqlite"

TABULKY = ["persons", "registration", "referents", "school",
           "advance", "submit", "publish_res"]

VIEW = """
CREATE VIEW participants AS
SELECT registration.person_id, registration.participant_id,
       registration.referent_id, registration.class, registration.teacher,
       registration.category, registration.solution_count, registration.rocnik,
       persons.name, persons.surname, persons.email, persons.born_date
FROM registration JOIN persons ON registration.person_id = persons.person_id
"""


def build(fo_sql=FO_SQL, out=OUT):
    sql = fo_sql.read_text(encoding="utf-8", errors="replace")
    out.parent.mkdir(parents=True, exist_ok=True)
    if out.exists():
        out.unlink()
    db = sqlite3.connect(out)
    counts = {}
    for t in TABULKY:
        cols = fosql.table_columns(sql, t)
        db.execute(f"CREATE TABLE {t} ({', '.join(cols)})")
        data = list(fosql.rows(sql, t))
        for r in data:
            if len(r) != len(cols):
                raise ValueError(f"{t}: řádek má {len(r)} hodnot, sloupců je {len(cols)}")
        db.executemany(
            f"INSERT INTO {t} VALUES ({','.join('?' * len(cols))})", data)
        counts[t] = len(data)
    db.execute(VIEW)
    db.commit()
    return db, counts


if __name__ == "__main__":
    _db, counts = build()
    for t, n in counts.items():
        print(f"  {t:14s} {n} řádků")
    print(f"-> {OUT}")
