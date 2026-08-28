#!/usr/bin/env python3
"""1. fáze migrace: věrný dump CouchDB shardů do data/export/.

Výstup je záměrně bez redakčních rozhodnutí — ta patří do 2. fáze, kde
se dají měnit bez sahání na binární soubory.

    python3 tools/couch_export.py [--src DIR] [--out DIR]
"""
import argparse
import collections
import hashlib
import json
import pathlib
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from couchdb.shard import Shard                                    # noqa: E402

SRC = "data/couchdb/shards"
OUT = "data/export"


def is_app_doc(doc_id):
    """Dokumenty webu mají _id ve tvaru cesty; uživatelé mají 'jmeno@'."""
    return doc_id.startswith("/")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", default=SRC)
    ap.add_argument("--out", default=OUT)
    args = ap.parse_args()

    src = pathlib.Path(args.src)
    out = pathlib.Path(args.out)
    blobs = out / "blobs"
    blobs.mkdir(parents=True, exist_ok=True)

    shards = sorted(src.glob("*/kkfo.*.couch"))
    if not shards:
        sys.exit(f"v {src} nejsou žádné shardy kkfo.*.couch")

    docs, tombstones, users, skipped = [], [], [], []
    seen = set()
    stats = collections.Counter()
    per_shard = []

    for path in shards:
        sh = Shard(path)
        live, dele = sh.counts
        name = sh.name
        n_live = n_del = 0

        for doc in sh.documents():
            if doc.doc_id in seen:
                sys.exit(f"duplicitní _id napříč shardy: {doc.doc_id}")
            seen.add(doc.doc_id)

            if doc.deleted:
                n_del += 1
                tombstones.append(doc.doc_id)
                continue
            n_live += 1

            if doc.doc_id.startswith("_"):
                # _design/eywa, _local/* — odvozená data, neexportují se
                skipped.append(doc.doc_id)
                continue
            if not is_app_doc(doc.doc_id):
                # uživatelské účty: nesolené MD5 heslo — do gitu nesmí
                users.append(doc.doc_id)
                stats["uzivatele"] += 1
                continue

            atts = []
            for att in doc.atts:
                data = sh.read_attachment(att)          # ověří délku i MD5
                digest = hashlib.sha256(data).hexdigest()
                blob = blobs / digest[:2] / f"{digest}.bin"
                if not blob.exists():
                    blob.parent.mkdir(parents=True, exist_ok=True)
                    blob.write_bytes(data)
                atts.append({
                    "name": att.name,
                    "content_type": att.content_type,
                    "length": len(data),
                    "stored_length": att.att_len,
                    "revpos": att.revpos,
                    "encoding": att.encoding,
                    "md5": att.md5.hex(),
                    "sha256": digest,
                    "blob": str(blob.relative_to(out)),
                })
                stats[f"priloha:{att.ext or '(bez pripony)'}"] += 1
                if len(data) == 0:
                    stats["priloha:nulova_delka"] += 1

            docs.append({
                "_id": doc.doc_id,
                "shard": name,
                "seq": doc.seq,
                "rev": doc.rev,
                "doc": doc.body,
                "attachments": atts,          # pořadí zachováno = resources[]
            })
            stats["class:" + str((doc.body or {}).get("class", "(bez class)"))] += 1

        assert n_live == live, f"{name}: nalezeno {n_live} živých, redukce říká {live}"
        assert n_del == dele, f"{name}: nalezeno {n_del} smazaných, redukce říká {dele}"
        per_shard.append((name, live, dele, sh.update_seq))

    docs.sort(key=lambda d: d["_id"])
    (out / "docs.json").write_text(
        json.dumps(docs, ensure_ascii=False, indent=1, sort_keys=False) + "\n",
        encoding="utf-8")
    (out / "tombstones.txt").write_text(
        "\n".join(sorted(tombstones)) + "\n", encoding="utf-8")

    lines = ["Export CouchDB kkfo -> data/export/", ""]
    lines.append(f"shardů: {len(shards)}")
    for name, live, dele, seq in per_shard:
        lines.append(f"  {name}  live={live:3d} deleted={dele:3d} update_seq={seq}")
    lines += [
        "",
        f"živých dokumentů celkem : {sum(p[1] for p in per_shard)}",
        f"náhrobků celkem         : {sum(p[2] for p in per_shard)}",
        f"exportovaných dokumentů : {len(docs)}",
        f"uživatelských účtů (zahozeno, hesla nesmí do gitu): {len(users)}",
        f"  {', '.join(sorted(users))}",
        f"přeskočeno (_design/_local): {len(skipped)}",
        f"  {', '.join(sorted(skipped))}" if skipped else "",
        f"unikátních blobů        : {sum(1 for _ in blobs.rglob('*.bin'))}",
        "",
        "statistiky:",
    ]
    for k, v in sorted(stats.items()):
        lines.append(f"  {k:48s} {v}")
    (out / "report.txt").write_text("\n".join(lines) + "\n", encoding="utf-8")

    print("\n".join(lines))


if __name__ == "__main__":
    main()
