#!/usr/bin/env python3
"""2. fáze migrace: data/export/ -> content/, files/, assets/*/_redirects.

Cesta dokumentu se zachovává 1:1 jako cesta v URL; stránka, která je zároveň
rodičem, dostane sourozenecký adresář.  Metadata jdou do YAML hlavičky,
tělo je HTML z přílohy content.html (u výsledků + převedená tabulka).

    python3 tools/migrate_content.py [--check | --write | --force]
"""
import argparse
import collections
import datetime
import hashlib
import json
import pathlib
import subprocess
import sys

import re
import urllib.parse

import yaml

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXPORT = ROOT / "data" / "export"
SITES = ("praha", "kvary")
DROP_PREFIXES = ("//praha/odpad", "//kvary/bin")

# Rok, ve kterém web naposledy žil (nejnovější editace 2026-05-28).  Šablona
# News porovnávala createdAt s date('Y'), takže datum novinek je závislé na
# čase renderu — tady se zmrazí.
NOW_YEAR = 2026

PRESENTER_TYPE = {
    "Eywa\\CMS\\Presenters\\TextPage": "page",
    "Eywa\\CMS\\Presenters\\Overview": "overview",
    "Eywa\\CMS\\Presenters\\News": "news",
    "Eywa\\CMS\\Presenters\\Results": "results",
    "Eywa\\CMS\\Presenters\\ResultsDatabase": "results",
    "Eywa\\CMS\\Presenters\\ResultsOverview": "results-overview",
    "Eywa\\CMS\\Presenters\\Redirect": "redirect",
    "Eywa\\CMS\\Presenters\\Link": "link",
    "Link": "link",
    "Eywa\\CMS\\Presenters\\Forum": "forum",       # zahazuje se, viz _redirects
}

# Adresáře, jejichž potomci jsou novinky (News.php:16 měl cestu natvrdo).
NEWS_CONTAINERS = ("/novinky-dir", "/stare-novinky")

# Vlastní domény: absolutní odkazy na ně se překlápí na kořenové,
# ostatní vlastní weby aspoň na https.
SELF_HOSTS = ("praha.fyzikalniolympiada.cz", "kvary.fyzikalniolympiada.cz")
HTTPS_HOSTS = ("fyzikalniolympiada.cz", "www.fyzikalniolympiada.cz",
               "osmo.fyzikalniolympiada.cz")

LINK_RE = re.compile(r'(?P<attr>\b(?:href|src)\s*=\s*)(?P<q>["\']?)(?P<url>[^"\'>\s]+)',
                     re.I)

MESICE = ["", "ledna", "února", "března", "dubna", "května", "června",
          "července", "srpna", "září", "října", "listopadu", "prosince"]


# ---------------------------------------------------------------- pomocné --

def dt(value):
    """{'date': '2016-03-04 10:00:00.000000'} -> datetime | None"""
    if not value or not isinstance(value, dict) or not value.get("date"):
        return None
    return datetime.datetime.strptime(value["date"][:19], "%Y-%m-%d %H:%M:%S")


def site_of(doc_id):
    parts = [p for p in doc_id.split("/") if p]
    return parts[0] if parts else None


def out_path(doc_id):
    """//praha/vysledky/61 -> ('praha', 'vysledky/61')  ;  //praha -> ('praha', '')"""
    parts = [p for p in doc_id.split("/") if p]
    return parts[0], "/".join(parts[1:])


def is_news_container(doc_id):
    return doc_id.endswith(NEWS_CONTAINERS)


def doc_type(body, doc_id, by_id):
    """Typ se odvozuje z presenteru — stejně jako to dělal router eywy
    (Route.php: $document->presenter).  Třída rozhoduje jen tam, kde
    presenter chybí, protože pár dokumentů má třídu zadanou omylem."""
    t = PRESENTER_TYPE.get(body.get("presenter") or "")
    cls = (body.get("class") or "").rsplit("\\", 1)[-1]
    if len([p for p in doc_id.split("/") if p]) == 1:
        return "site"                       # //praha, //kvary
    if cls == "Directory" and t is None:
        return "dir"
    if t == "news":
        # Presenter News zdědily i jednotlivé novinky; skutečná rozcestníková
        # stránka je ta, pod kterou visí adresář s novinkami.
        if not any(doc_id + c in by_id for c in NEWS_CONTAINERS):
            return "page"
    return t or "page"


# ------------------------------------------------------------------ hlavní --

class Migration:
    def __init__(self, mode):
        self.mode = mode
        self.docs = json.loads((EXPORT / "docs.json").read_text(encoding="utf-8"))
        self.kept = [d for d in self.docs if self._keep(d)]
        self.by_id = {d["_id"]: d for d in self.kept}
        self.manifest = {}
        self.report = collections.Counter()
        self.notes = []
        self.redirects = collections.defaultdict(list)
        self.xls_fail = []
        self.link_targets = []

    @staticmethod
    def _keep(d):
        i = d["_id"]
        if site_of(i) not in SITES:
            return False
        return not any(i == p or i.startswith(p + "/") for p in DROP_PREFIXES)

    # -- přílohy ---------------------------------------------------------
    def blob(self, att):
        return (EXPORT / att["blob"]).read_bytes()

    def html_of(self, d):
        for a in d["attachments"]:
            if a["name"] == "content.html":
                return self.blob(a).decode("utf-8", "replace")
        return ""

    def binaries(self, d):
        """Nepřílohové soubory v ULOŽENÉM pořadí = eywa $document->resources."""
        return [a for a in d["attachments"] if a["name"] != "content.html"]

    # -- odkazy ----------------------------------------------------------
    def fix_links(self, html, base, doc_id):
        """Přepíše odkazy na kořenové/absolutní tvary.

        Relativní odkazy se rozvíjejí proti `base` — u novinek je to adresa
        rozcestníku novinek, protože právě tam se novinky vykreslovaly inline
        a proti němu je autoři psali (`../vysledky/...` -> `/vysledky/...`)."""
        def sub(m):
            url = m.group("url")
            new = self.fix_one(url, base, doc_id)
            return f'{m.group("attr")}{m.group("q")}{new}' if new != url else m.group(0)
        return LINK_RE.sub(sub, html)

    def fix_one(self, url, base, doc_id):
        if url.startswith(("mailto:", "#", "javascript:", "data:", "tel:")):
            return url
        parts = urllib.parse.urlsplit(url)
        if parts.scheme in ("http", "https"):
            if parts.hostname in SELF_HOSTS:
                path = urllib.parse.urlunsplit(("", "", parts.path or "/",
                                                parts.query, parts.fragment))
                return self.tidy(path, doc_id)
            if parts.scheme == "http" and parts.hostname in HTTPS_HOSTS:
                return urllib.parse.urlunsplit(("https",) + tuple(parts)[1:])
            return url
        if parts.scheme:
            return url
        absolute = urllib.parse.urljoin(base, url)
        return self.tidy(absolute, doc_id)

    def tidy(self, url, doc_id):
        """Odstraní koncové lomítko (build zapisuje <slug>.html) a ohlásí
        interní odkazy, které nikam nevedou."""
        parts = urllib.parse.urlsplit(url)
        path = parts.path
        if len(path) > 1 and path.endswith("/"):
            path = path.rstrip("/")
        out = urllib.parse.urlunsplit(("", "", path, parts.query, parts.fragment))
        self.link_targets.append((doc_id, path))
        return out

    # -- novinky ---------------------------------------------------------
    def news_dates(self):
        """Zmrazí datum novinek podle algoritmu News.default.phtml:14-28."""
        out = {}
        containers = sorted(i for i in self.by_id if is_news_container(i))
        for parent in containers:
            kids = self.children(parent)
            prev = None
            for d in kids:
                b = d["doc"]
                created = dt(b.get("createdAt"))
                changes = b.get("changes") or []
                chosen = None
                if created and created.year == NOW_YEAR:
                    chosen = created
                if chosen is None:
                    for ch in reversed(changes):
                        cd = dt(ch.get("datetime"))
                        if cd and (prev is None or prev > cd):
                            chosen = cd
                            break
                if chosen is None:
                    chosen = created
                prev = chosen
                if chosen:
                    out[d["_id"]] = chosen
        return out

    def children(self, parent):
        kids = [d for d in self.kept
                if d["_id"].startswith(parent + "/")
                and "/" not in d["_id"][len(parent) + 1:]]
        return sorted(kids, key=lambda d: ((d["doc"].get("position") or 0),
                                           d["doc"].get("name") or ""))

    # -- běh -------------------------------------------------------------
    def copy_recovered_doc(self):
        """web/doc/ ze starého serveru — na něj míří odkazy v novinkách.
        Symlinky se přeskakují (mířily mimo docroot; jeden cíl má 721 MB,
        což je nad limitem Cloudflare Pages)."""
        src = ROOT / ".recovered" / "doc"
        if not src.is_dir():
            self.notes.append("POZOR: .recovered/doc chybí, odkazy na /doc/ nebudou fungovat")
            return
        for f in sorted(src.iterdir()):
            if f.is_symlink() or not f.is_file():
                self.notes.append(f"přeskočen symlink/adresář: doc/{f.name}")
                continue
            self.write(ROOT / "files" / "praha" / "doc" / f.name, f.read_bytes(), binary=True)
            self.report["soubor:doc"] += 1

    def run(self):
        dates = self.news_dates()
        for d in sorted(self.kept, key=lambda x: x["_id"]):
            self.emit(d, dates)
        self.copy_recovered_doc()
        self.emit_redirects()
        self.check_links()
        self.write_manifest()
        self.summary()

    def emit(self, d, dates):
        body = d["doc"] or {}
        typ = doc_type(body, d["_id"], self.by_id)
        site, rel = out_path(d["_id"])
        self.report["typ:" + typ] += 1

        if typ == "forum":
            self.redirects[site].append(("/" + rel, "/kontakty", 301))
            self.notes.append(f"forum zahozeno: {d['_id']} -> /kontakty")
            return

        html = self.html_of(d)
        if html:
            base = self.link_base(d["_id"])
            html = self.fix_links(html, base, d["_id"])
        fm = collections.OrderedDict()
        fm["type"] = typ
        fm["title"] = body.get("name") or ""
        heading = (body.get("heading") or "").strip()
        if heading:
            fm["heading"] = heading
        if body.get("position"):
            fm["position"] = body["position"]

        if typ == "redirect":
            fm["location"] = self.upgrade_scheme(body.get("location") or "")
            fm["code"] = int(body.get("code") or 302)
            if fm["location"]:
                self.redirects[site].append(("/" + rel, fm["location"], fm["code"]))
            else:
                self.notes.append(f"redirect bez cíle: {d['_id']}")
        if typ == "news":
            for c in NEWS_CONTAINERS:
                if d["_id"] + c in self.by_id:
                    fm["novinky"] = self.internal_url(d["_id"] + c)
                    break
        if typ in ("site", "link"):
            dest = body.get("destination")
            if dest:
                fm["destination"] = self.internal_url(dest)
            sub = body.get("subtitle")
            if sub:
                fm["subtitle"] = sub

        # binární přílohy -> files/<web>/<cesta>[-N].<ext>
        bins = self.binaries(d)
        for n, att in enumerate(bins, start=1):
            ext = att["name"].rsplit(".", 1)[-1].lower() if "." in att["name"] else "bin"
            suffix = "" if n == 1 else f"-{n}"
            fpath = ROOT / "files" / site / f"{rel}{suffix}.{ext}"
            self.write(fpath, self.blob(att), binary=True)
            if n == 1:
                fm["soubor"] = f"/{rel}{suffix}.{ext}"
                fm["soubor_nazev"] = att["name"]
            self.report["soubor:" + ext] += 1

        # tabulka z XLS se vkládá za tělo (pořadí dle Results.default.phtml)
        if typ == "results" and bins and bins[0]["name"].lower().endswith(".xls"):
            table = self.xls_table(EXPORT / bins[0]["blob"], d["_id"])
            if table:
                html = html.rstrip() + "\n" + table + "\n"

        if d["_id"] in dates:
            fm["date"] = dates[d["_id"]].strftime("%Y-%m-%d %H:%M")
        changes = body.get("changes") or []
        if changes:
            fm["author"] = changes[0].get("identity") or ""
        created, modified = dt(body.get("createdAt")), dt(body.get("modifiedAt"))
        if created:
            fm["created"] = created.strftime("%Y-%m-%d %H:%M:%S")
        if modified:
            fm["modified"] = modified.strftime("%Y-%m-%d %H:%M:%S")
        fm["source"] = d["_id"]

        target = ROOT / "content" / site / (f"{rel}.html" if rel else "index.html")
        self.write(target, self.render(fm, html))

    @staticmethod
    def upgrade_scheme(url):
        """Cíle na vlastních doménách povýší na https (weby FO ho mají)."""
        parts = urllib.parse.urlsplit(url)
        if parts.scheme == "http" and parts.hostname in HTTPS_HOSTS + SELF_HOSTS:
            return urllib.parse.urlunsplit(("https",) + tuple(parts)[1:])
        return url

    def link_base(self, doc_id):
        """Základ pro rozvinutí relativních odkazů — vždy s koncovým
        lomítkem, protože eywa generovala adresy s ním ({dlink})."""
        parent = doc_id.rsplit("/", 1)[0]
        if is_news_container(parent):
            doc_id = parent.rsplit("/", 1)[0]      # rozcestník novinek
        return self.internal_url(doc_id).rstrip("/") + "/"

    def internal_url(self, dest):
        """//praha/aktualni-rocnik -> /aktualni-rocnik ; externí nechá být."""
        if dest.startswith("//"):
            parts = [p for p in dest.split("/") if p]
            return "/" + "/".join(parts[1:])
        return dest

    def xls_table(self, blob_path, doc_id):
        r = subprocess.run(["php", "tools/xls2html.php", str(blob_path)],
                           capture_output=True, text=True, cwd=ROOT)
        if r.returncode != 0:
            self.xls_fail.append((doc_id, r.stderr.strip().splitlines()[:1]))
            return "<!-- migrace: tabulku se nepodařilo vygenerovat -->"
        return r.stdout.strip()

    @staticmethod
    def render(fm, html):
        head = yaml.safe_dump(dict(fm), allow_unicode=True, sort_keys=False,
                              default_flow_style=False, width=10 ** 6)
        return "---\n" + head + "---\n" + html.strip() + "\n"

    def emit_redirects(self):
        extra = {
            "praha": [("/vysledky/rocnik-53/*", "/vysledky/53-rocnik/:splat", 301),
                      ("/vysledky/60-rocnik-2017-2018/*", "/vysledky/60-rocnik-2018-2019/:splat", 301)],
            "kvary": [],
        }
        for site in SITES:
            rules = sorted(self.redirects[site]) + extra.get(site, [])
            lines = ["# Generováno tools/migrate_content.py — needitovat ručně.",
                     "# Ruční pravidla patří do assets/<web>/_redirects-rucni.", ""]
            lines += [f"{src}  {dst}  {code}" for src, dst, code in rules]
            self.write(ROOT / "assets" / site / "_redirects-generovane",
                       "\n".join(lines) + "\n")
            self.report[f"presmerovani:{site}"] = len(rules)

    # -- zápis s ochranou ručních úprav -----------------------------------
    def write(self, path, data, binary=False):
        raw = data if binary else data.encode("utf-8")
        digest = hashlib.sha256(raw).hexdigest()
        rel = str(path.relative_to(ROOT))
        self.manifest[rel] = digest

        if self.mode == "check":
            self.report["by se zapsalo"] += 1
            return
        if path.exists() and self.mode != "force":
            old = hashlib.sha256(path.read_bytes()).hexdigest()
            prev = self.prev_manifest.get(rel)
            if prev and old != prev:
                self.report["přeskočeno (ruční úprava)"] += 1
                self.notes.append(f"ručně upraveno, nepřepisuji: {rel}")
                return
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(raw)
        self.report["zapsáno"] += 1

    prev_manifest = {}

    def write_manifest(self):
        if self.mode == "check":
            return
        p = EXPORT / "manifest.json"
        p.write_text(json.dumps(self.manifest, ensure_ascii=False, indent=1,
                                sort_keys=True) + "\n", encoding="utf-8")

    def check_links(self):
        """Ověří, že každý interní odkaz míří na vygenerovanou stránku,
        na soubor v files/ nebo na pravidlo v _redirects."""
        routes = set()
        for rel in self.manifest:
            if rel.startswith("content/"):
                parts = rel[len("content/"):-len(".html")].split("/", 1)
                if len(parts) == 2:
                    routes.add("/" + parts[1] if parts[1] != "index" else "/")
            elif rel.startswith("files/"):
                parts = rel[len("files/"):].split("/", 1)
                if len(parts) == 2:
                    routes.add("/" + parts[1])
        for site in SITES:
            for src, _dst, _c in self.redirects[site]:
                routes.add(src)
        dead = sorted({(doc, t) for doc, t in self.link_targets if t not in routes})
        self.report["mrtvých interních odkazů"] = len(dead)
        for doc, t in dead:
            self.notes.append(f"mrtvý odkaz {t}  (v {doc})")

    def summary(self):
        print(f"dokumentů v exportu : {len(self.docs)}")
        print(f"po filtru           : {len(self.kept)}")
        for k, v in sorted(self.report.items()):
            print(f"  {k:34s} {v}")
        if self.xls_fail:
            print(f"\nNEPŘEVEDENÉ XLS ({len(self.xls_fail)}):")
            for x in self.xls_fail:
                print("   ", x)
        if self.notes:
            print(f"\npoznámky ({len(self.notes)}):")
            for n in self.notes[:20]:
                print("   ", n)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_const", const="check", dest="mode")
    ap.add_argument("--write", action="store_const", const="write", dest="mode")
    ap.add_argument("--force", action="store_const", const="force", dest="mode")
    args = ap.parse_args()
    m = Migration(args.mode or "check")
    mp = EXPORT / "manifest.json"
    if mp.exists():
        m.prev_manifest = json.loads(mp.read_text(encoding="utf-8"))
    m.run()
    return 1 if m.xls_fail else 0


if __name__ == "__main__":
    sys.exit(main())
