"""Dekomprese raw Snappy (bez rámce), čistě ve standardní knihovně."""


def decompress(src):
    n, pos = _varint(src, 0)
    out = bytearray()
    end = len(src)
    while pos < end:
        tag = src[pos]
        pos += 1
        kind = tag & 0x03
        if kind == 0:                                    # literál
            ln = tag >> 2
            if ln < 60:
                ln += 1
            else:
                extra = ln - 59
                ln = int.from_bytes(src[pos:pos + extra], "little") + 1
                pos += extra
            out += src[pos:pos + ln]
            pos += ln
            continue
        if kind == 1:                                    # kopie, 1B offset
            ln = 4 + ((tag >> 2) & 0x07)
            off = ((tag >> 5) << 8) | src[pos]
            pos += 1
        elif kind == 2:                                  # kopie, 2B offset
            ln = (tag >> 2) + 1
            off = int.from_bytes(src[pos:pos + 2], "little")
            pos += 2
        else:                                            # kopie, 4B offset
            ln = (tag >> 2) + 1
            off = int.from_bytes(src[pos:pos + 4], "little")
            pos += 4
        if off == 0 or off > len(out):
            raise ValueError(f"neplatný snappy offset {off}")
        start = len(out) - off
        for k in range(ln):                              # překryv je povolený
            out.append(out[start + k])
    if len(out) != n:
        raise ValueError(f"snappy: čekáno {n} B, dekódováno {len(out)}")
    return bytes(out)


def _varint(b, i):
    shift = 0
    val = 0
    while True:
        c = b[i]
        i += 1
        val |= (c & 0x7F) << shift
        if not c & 0x80:
            return val, i
        shift += 7
