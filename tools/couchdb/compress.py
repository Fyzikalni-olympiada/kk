"""couch_compress: 0x01 => snappy(term_to_binary), 0x83 (131) => holý ETF."""
from . import etf, snappy

SNAPPY_PREFIX = 1
TERM_PREFIX = 131


def decompress(data):
    if not data:
        raise ValueError("prázdný komprimovaný blok")
    if data[0] == SNAPPY_PREFIX:
        return etf.binary_to_term(snappy.decompress(data[1:]))
    if data[0] == TERM_PREFIX:
        return etf.binary_to_term(data)
    raise ValueError(f"neznámý prefix komprese {data[0]}")
