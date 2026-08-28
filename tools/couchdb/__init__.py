"""Čtečka souborů CouchDB (.couch) bez závislostí mimo standardní knihovnu.

Použití:
    from couchdb.shard import Shard
    s = Shard(pathlib.Path("data/couchdb/shards/.../kkfo.1566579343.couch"))
    for doc in s.documents():
        ...
"""
