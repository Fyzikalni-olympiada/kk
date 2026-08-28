"""Blokové rámcování souborů CouchDB.

Soubor je rozdělen na bloky po 4096 B; každý blok začíná jedním prefixovým
bajtem (0x00 data, 0x01 hlavička).  Čtení proto musí prefixy přeskakovat.
"""
import hashlib

from . import etf

BLOCK = 4096


def unframe(buf, pos, n):
    """Přečte n bajtů od absolutní pozice pos a přeskočí prefixy bloků."""
    out = bytearray()
    while n > 0:
        if pos % BLOCK == 0:
            pos += 1
        take = min(n, BLOCK - (pos % BLOCK))
        chunk = buf[pos:pos + take]
        if len(chunk) < take:
            raise EOFError(f"soubor končí uprostřed chunku na {pos}")
        out += chunk
        pos += take
        n -= take
    return bytes(out), pos


def read_chunk(buf, pos):
    """Přečte chunk: 4B délka BE; nastavený horní bit => délka & 0x7fffffff
    a následuje 16B MD5 (ověří se rovnou tady)."""
    head, p = unframe(buf, pos, 4)
    size = int.from_bytes(head, "big")
    md5 = None
    if size & 0x80000000:
        size &= 0x7FFFFFFF
        md5, p = unframe(buf, p, 16)
    data, p = unframe(buf, p, size)
    if md5 is not None and hashlib.md5(data).digest() != md5:
        raise ValueError(f"MD5 chunku na {pos} nesouhlasí")
    return data


def find_last_header(buf):
    """Najde poslední platnou hlavičku: hledá pozpátku blok s prefixem 0x01.

    Hlavička je zapsána jako [0x01][4B délka][16B MD5][term_to_binary].
    Vrací (pozice, dekódovaný term)."""
    p = (len(buf) // BLOCK) * BLOCK
    while p >= 0:
        if p < len(buf) and buf[p] == 1:
            try:
                size = int.from_bytes(buf[p + 1:p + 5], "big")
                if 16 < size < len(buf):
                    data, _ = unframe(buf, p + 5, size)
                    md5, term_bin = data[:16], data[16:]
                    if hashlib.md5(term_bin).digest() == md5:
                        return p, etf.binary_to_term(term_bin)
            except (EOFError, ValueError, IndexError):
                pass
        p -= BLOCK
    raise ValueError("v souboru není platná hlavička")
