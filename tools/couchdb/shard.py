"""Jeden .couch soubor (shard nebo databáze CouchDB 1.x)."""
from dataclasses import dataclass, field

from . import attachments, blocks, btree, compress, etf, revtree


@dataclass
class Document:
    doc_id: str
    seq: int
    deleted: bool
    rev: str
    body: dict = None
    atts: list = field(default_factory=list)


class Shard:
    def __init__(self, path):
        self.name = path.parent.name if hasattr(path, 'parent') else str(path)
        with open(path, "rb") as fh:
            self.buf = fh.read()
        _pos, self.header = blocks.find_last_header(self.buf)
        if str(self.header[0]) != "db_header":
            raise ValueError(f"{path}: hlavička je {self.header[0]!r}, ne db_header")

    # -- hlavička -------------------------------------------------------
    @property
    def update_seq(self):
        return self.header[2]

    @property
    def id_tree_state(self):
        return self.header[4]

    @property
    def root_ptr(self):
        st = self.id_tree_state
        return st[0] if isinstance(st, tuple) else None

    @property
    def counts(self):
        """(live, deleted) z redukce by_id stromu."""
        st = self.id_tree_state
        if not isinstance(st, tuple) or len(st) < 2 or not isinstance(st[1], tuple):
            return (0, 0)
        red = st[1]
        return (red[0], red[1])

    # -- dokumenty ------------------------------------------------------
    def entries(self):
        if self.root_ptr is None:
            return
        yield from btree.walk(self.buf, self.root_ptr)

    def documents(self):
        for key, value in self.entries():
            doc_id = key.decode("utf-8") if isinstance(key, bytes) else str(key)
            seq, deleted, _sizes, disk_tree = value[0], value[1], value[2], value[3]
            leaf = revtree.winning_leaf(disk_tree)
            if leaf is None:
                continue
            depth, rev_id, leaf_val = leaf
            # list rev stromu: {Del, BodyPtr, Seq, Sizes, StreamTerms}
            body_ptr = leaf_val[1]
            doc = Document(
                doc_id=doc_id, seq=seq, deleted=bool(deleted),
                rev=f"{depth}-{rev_id.hex() if isinstance(rev_id, bytes) else rev_id}",
            )
            if not doc.deleted and isinstance(body_ptr, int):
                doc.body, doc.atts = self.read_body(body_ptr)
            yield doc

    def read_body(self, ptr):
        """Body chunk je {komprimované_telo, komprimované_prilohy}.

        Pořadí příloh se zachovává — je to jediná stopa po tom, co eywa
        viděla jako $document->resources[0]."""
        data = blocks.read_chunk(self.buf, ptr)
        term = compress.decompress(data)
        if not (isinstance(term, tuple) and len(term) == 2):
            raise ValueError(f"body chunk na {ptr} není dvojice")
        body = etf.to_ejson(compress.decompress(term[0]))
        atts_term = compress.decompress(term[1])
        return body, attachments.parse(atts_term) if atts_term else []

    def read_attachment(self, att):
        return attachments.read(self.buf, att)
