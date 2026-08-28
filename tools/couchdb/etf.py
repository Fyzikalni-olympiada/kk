"""Dekodér Erlang External Term Format (jen tagy, které CouchDB používá)."""
import struct


class Atom(str):
    """Erlang atom; podtřída str, aby se dal porovnávat s 'db_header' apod."""
    __slots__ = ()


def binary_to_term(b):
    if not b or b[0] != 131:
        raise ValueError("chybí ETF verze 131")
    term, i = _read(b, 1)
    return term


def _read(b, i):
    tag = b[i]
    i += 1

    if tag == 97:                                    # SMALL_INTEGER_EXT
        return b[i], i + 1
    if tag == 98:                                    # INTEGER_EXT
        return struct.unpack_from(">i", b, i)[0], i + 4
    if tag == 70:                                    # NEW_FLOAT_EXT
        return struct.unpack_from(">d", b, i)[0], i + 8
    if tag == 100:                                   # ATOM_EXT
        n = struct.unpack_from(">H", b, i)[0]
        i += 2
        return Atom(b[i:i + n].decode("latin-1")), i + n
    if tag == 118:                                   # ATOM_UTF8_EXT
        n = struct.unpack_from(">H", b, i)[0]
        i += 2
        return Atom(b[i:i + n].decode("utf-8")), i + n
    if tag == 115:                                   # SMALL_ATOM_EXT
        n = b[i]
        i += 1
        return Atom(b[i:i + n].decode("latin-1")), i + n
    if tag == 119:                                   # SMALL_ATOM_UTF8_EXT
        n = b[i]
        i += 1
        return Atom(b[i:i + n].decode("utf-8")), i + n
    if tag == 104:                                   # SMALL_TUPLE_EXT
        arity = b[i]
        i += 1
        return _tuple(b, i, arity)
    if tag == 105:                                   # LARGE_TUPLE_EXT
        arity = struct.unpack_from(">I", b, i)[0]
        return _tuple(b, i + 4, arity)
    if tag == 106:                                   # NIL_EXT
        return [], i
    if tag == 107:                                   # STRING_EXT (seznam malých intů)
        n = struct.unpack_from(">H", b, i)[0]
        i += 2
        return list(b[i:i + n]), i + n
    if tag == 108:                                   # LIST_EXT
        n = struct.unpack_from(">I", b, i)[0]
        i += 4
        items = []
        for _ in range(n):
            v, i = _read(b, i)
            items.append(v)
        tail, i = _read(b, i)                        # obvykle NIL
        if tail != []:
            items.append(tail)                       # nesprávný seznam
        return items, i
    if tag == 109:                                   # BINARY_EXT
        n = struct.unpack_from(">I", b, i)[0]
        i += 4
        return b[i:i + n], i + n
    if tag == 110:                                   # SMALL_BIG_EXT
        n = b[i]
        return _big(b, i + 2, n, b[i + 1])
    if tag == 111:                                   # LARGE_BIG_EXT
        n = struct.unpack_from(">I", b, i)[0]
        return _big(b, i + 5, n, b[i + 4])
    if tag == 77:                                    # BIT_BINARY_EXT
        n = struct.unpack_from(">I", b, i)[0]
        i += 5                                       # 4B délka + 1B bity
        return b[i:i + n], i + n

    raise ValueError(f"neznámý ETF tag {tag} na pozici {i - 1}")


def _tuple(b, i, arity):
    items = []
    for _ in range(arity):
        v, i = _read(b, i)
        items.append(v)
    return tuple(items), i


def _big(b, i, n, sign):
    v = int.from_bytes(b[i:i + n], "little")
    return (-v if sign else v), i + n


def to_ejson(term):
    """Převede EJSON reprezentaci CouchDB na běžné pythonní typy.

    Objekt je {[{Klíč, Hodnota}, ...]} — tuple arity 1 kolem proplistu."""
    if isinstance(term, tuple) and len(term) == 1 and isinstance(term[0], list):
        out = {}
        for pair in term[0]:
            if not (isinstance(pair, tuple) and len(pair) == 2):
                raise ValueError(f"proplist čekal dvojici, dostal {pair!r}")
            out[_key(pair[0])] = to_ejson(pair[1])
        return out
    if isinstance(term, list):
        return [to_ejson(x) for x in term]
    if isinstance(term, Atom):
        return {"true": True, "false": False, "null": None}.get(str(term), str(term))
    if isinstance(term, bytes):
        return term.decode("utf-8")
    return term


def _key(k):
    return k.decode("utf-8") if isinstance(k, bytes) else str(k)
