"""Výběr vítězné revize z DiskRevTree.

Struktura: [{Start, Node}], kde Node = {RevId, Hodnota, Potomci} — tedy
vnější prvek má aritu 2, uzel aritu 3.  Sestupuje se do entry[1].
"""
def leaves(tree):
    """Vydá (hloubka, rev_id, hodnota) pro každý list."""
    for entry in tree:
        if not (isinstance(entry, tuple) and len(entry) == 2):
            raise ValueError(f"kořen rev stromu má aritu {len(entry)}, čekána 2")
        start, node = entry
        yield from _walk(node, start)


def _walk(node, depth):
    if not (isinstance(node, tuple) and len(node) == 3):
        raise ValueError(f"uzel rev stromu má aritu {len(node)}, čekána 3")
    rev_id, value, children = node
    if children:
        for child in children:
            yield from _walk(child, depth + 1)
    else:
        yield depth, rev_id, value


def winning_leaf(tree):
    """Vítěz podle CouchDB: nesmazaný před smazaným, pak nejhlubší,
    při shodě největší rev hash.

    Pořadí (not Deleted, Depth, RevId) je podstatné — dokument s hlubší
    smazanou revizí a mělčí živou je *živý*; opačné pořadí by ho označilo
    za smazaný a rozešlo by se s redukcí by_id stromu.

    Listy s ?REV_MISSING se přeskakují (v tomto dumpu se nevyskytují)."""
    best = None
    for depth, rev_id, value in leaves(tree):
        if not isinstance(value, tuple):
            continue                      # ?REV_MISSING nebo zástupný list
        deleted = bool(value[0])
        key = (not deleted, depth, rev_id if isinstance(rev_id, bytes) else b"")
        if best is None or key > best[0]:
            best = (key, depth, rev_id, value)
    if best is None:
        return None
    _key, depth, rev_id, value = best
    return depth, rev_id, value
