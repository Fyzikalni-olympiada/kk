"""Průchod couch_btree.  Uzel je {kp_node|kv_node, [{Klíč, Hodnota}, ...]}."""
from . import blocks, compress


def read_node(buf, ptr):
    data = blocks.read_chunk(buf, ptr)
    node = compress.decompress(data)
    if not (isinstance(node, tuple) and len(node) == 2):
        raise ValueError(f"uzel na {ptr} není dvojice: {type(node)}")
    kind, items = node
    return str(kind), items


def walk(buf, ptr):
    """Vydá (klíč, hodnota) všech listů podstromu, v pořadí klíčů."""
    kind, items = read_node(buf, ptr)
    if kind == "kv_node":
        for key, value in items:
            yield key, value
    elif kind == "kp_node":
        for _key, pointer in items:
            # ukazatel je {Pos, Reduction} nebo {Pos, Reduction, SubtreeSize}
            child = pointer[0] if isinstance(pointer, tuple) else pointer
            yield from walk(buf, child)
    else:
        raise ValueError(f"neznámý typ uzlu {kind!r}")
