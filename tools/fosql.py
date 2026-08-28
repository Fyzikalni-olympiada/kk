"""Čtečka mysqldump souboru fo.sql.

Nutná proto, že dump má jen 939 řádků — jednotlivé INSERTy jsou několik
megabajtů na jednom řádku, takže řádkové nástroje jsou k ničemu.
"""
import re


def table_columns(sql, table):
    m = re.search(r"CREATE TABLE `%s` \((.*?)\n\) ENGINE" % re.escape(table), sql, re.S)
    if not m:
        raise KeyError(f"tabulka {table} v dumpu není")
    return [l.strip().split("`")[1] for l in m.group(1).split("\n")
            if l.strip().startswith("`")]


def rows(sql, table):
    """Vydá n-tice hodnot ze všech INSERT INTO `table` VALUES (...),(...);"""
    for m in re.finditer(r"INSERT INTO `%s` VALUES (.*?);\n" % re.escape(table),
                         sql, re.S):
        for tup in _split_tuples(m.group(1)):
            yield _values(tup)


def _split_tuples(body):
    """Rozdělí `(...),(...)` na vrcholové n-tice; respektuje uvozovky."""
    out, depth, start = [], 0, None
    in_str, esc, quote = False, False, ""
    for i, ch in enumerate(body):
        if in_str:
            if esc:
                esc = False
            elif ch == "\\":
                esc = True
            elif ch == quote:
                in_str = False
            continue
        if ch in "'\"":
            in_str, quote = True, ch
        elif ch == "(":
            if depth == 0:
                start = i + 1
            depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0:
                out.append(body[start:i])
    return out


def _values(tup):
    """Rozdělí obsah jedné n-tice na hodnoty."""
    out, cur = [], []
    in_str, esc, quote = False, False, ""
    for ch in tup:
        if in_str:
            if esc:
                cur.append(_unescape_char(ch))
                esc = False
            elif ch == "\\":
                esc = True
            elif ch == quote:
                in_str = False
            else:
                cur.append(ch)
            continue
        if ch in "'\"":
            in_str, quote = True, ch
            cur.append("\0STR")          # značka, že jde o řetězec
        elif ch == ",":
            out.append(_finish(cur))
            cur = []
        else:
            cur.append(ch)
    out.append(_finish(cur))
    return out


def _finish(chars):
    s = "".join(chars)
    if s.startswith("\0STR"):
        return s[4:]
    s = s.strip()
    if s == "NULL":
        return None
    try:
        return int(s)
    except ValueError:
        try:
            return float(s)
        except ValueError:
            return s


_ESCAPES = {"n": "\n", "r": "\r", "t": "\t", "0": "\0", "Z": "\x1a",
            "\\": "\\", "'": "'", '"': '"'}


def _unescape_char(ch):
    return _ESCAPES.get(ch, ch)
