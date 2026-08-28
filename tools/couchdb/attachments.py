"""Přílohy dokumentu.

Metadata žijí v *druhém* prvku body chunku, ne v listu rev stromu:
    {Jméno, Typ, Ukazatele, AttLen, DiskLen, RevPos, Md5, Enc}
kde AttLen je délka uložených bajtů, DiskLen délka po dekompresi
a Md5 je otisk *uložených* (zakódovaných) bajtů.
"""
import hashlib
import zlib
from dataclasses import dataclass

from . import blocks


@dataclass
class Attachment:
    name: str
    content_type: str
    ptrs: list
    att_len: int
    disk_len: int
    revpos: int
    md5: bytes
    encoding: str

    @property
    def is_gzip(self):
        return self.encoding == "gzip"

    @property
    def ext(self):
        return self.name.rsplit(".", 1)[-1].lower() if "." in self.name else ""


def parse(disk_atts):
    out = []
    for att in disk_atts:
        if len(att) < 7:
            raise ValueError(f"příloha má jen {len(att)} položek: {att!r}")
        name, ctype, ptrs, att_len, disk_len, revpos, md5 = att[:7]
        enc = att[7] if len(att) > 7 else "identity"
        out.append(Attachment(
            name=_s(name), content_type=_s(ctype), ptrs=_ptr_list(ptrs),
            att_len=att_len, disk_len=disk_len, revpos=revpos,
            md5=md5 if isinstance(md5, (bytes, bytearray)) else b"",
            encoding=str(enc),
        ))
    return out


def _s(v):
    return v.decode("utf-8") if isinstance(v, (bytes, bytearray)) else str(v)


def _ptr_list(ptrs):
    """Ukazatele streamu [{Pos, Len}, ...]; prázdný seznam = nulová délka."""
    if not ptrs:
        return []
    out = []
    for p in list(ptrs):
        if isinstance(p, tuple) and len(p) >= 2:
            out.append((p[0], p[1]))
        elif isinstance(p, int):
            out.append((p, None))
        else:
            raise ValueError(f"neznámý tvar ukazatele streamu: {p!r}")
    return out


def read(buf, att):
    """Vrátí dekódované bajty přílohy.

    Prázdný seznam ukazatelů znamená přílohu nulové délky — to je legitimní
    stav (v tomto dumpu 109× content.html), ne chyba."""
    raw = bytearray()
    for pos, _ln in att.ptrs:
        data = blocks.read_chunk(buf, pos)
        raw += data
    raw = bytes(raw)

    # Md5 i att_len se vztahují k ULOŽENÝM (zakódovaným) bajtům,
    # disk_len k obsahu po dekompresi.  Ověřeno na všech 143 gzip přílohách.
    if att.att_len is not None and len(raw) != att.att_len:
        raise ValueError(f"{att.name!r}: uloženo {len(raw)} B, hlavička říká {att.att_len}")
    if att.md5 and hashlib.md5(raw).digest() != att.md5:
        raise ValueError(f"{att.name!r}: MD5 uložených bajtů nesouhlasí")

    out = zlib.decompress(raw, 31) if (att.is_gzip and raw) else raw

    if att.disk_len is not None and len(out) != att.disk_len:
        raise ValueError(f"{att.name!r}: dekódováno {len(out)} B, čekáno {att.disk_len}")
    return out
