/** كاتب ZIP بسيط بطريقة التخزين (بلا ضغط) — يكفي لإنتاج ملفّات docx و xlsx صحيحة. */

const CRC_TABLE = (() => {
  const t = new Uint32Array(256)
  for (let i = 0; i < 256; i++) {
    let c = i
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    t[i] = c >>> 0
  }
  return t
})()

function crc32(buf: Uint8Array): number {
  let c = 0xffffffff
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8)
  return (c ^ 0xffffffff) >>> 0
}

const enc = new TextEncoder()

interface Entry { name: string; data: Uint8Array; crc: number; offset: number }

export function zipSync(files: { name: string; content: string | Uint8Array }[]): Blob {
  const chunks: Uint8Array[] = []
  const entries: Entry[] = []
  let offset = 0

  const push = (u: Uint8Array) => { chunks.push(u); offset += u.length }

  for (const f of files) {
    const data = typeof f.content === 'string' ? enc.encode(f.content) : f.content
    const nameBytes = enc.encode(f.name)
    const crc = crc32(data)
    const local = new Uint8Array(30 + nameBytes.length)
    const dv = new DataView(local.buffer)
    dv.setUint32(0, 0x04034b50, true)   // signature
    dv.setUint16(4, 20, true)           // version needed
    dv.setUint16(6, 0x0800, true)       // flags: UTF-8 names
    dv.setUint16(8, 0, true)            // method: store
    dv.setUint16(10, 0, true)           // time
    dv.setUint16(12, 0x2821, true)      // date (2000-01-01)
    dv.setUint32(14, crc, true)
    dv.setUint32(18, data.length, true)
    dv.setUint32(22, data.length, true)
    dv.setUint16(26, nameBytes.length, true)
    dv.setUint16(28, 0, true)
    local.set(nameBytes, 30)
    entries.push({ name: f.name, data, crc, offset })
    push(local)
    push(data)
  }

  const cdStart = offset
  for (const e of entries) {
    const nameBytes = enc.encode(e.name)
    const cd = new Uint8Array(46 + nameBytes.length)
    const dv = new DataView(cd.buffer)
    dv.setUint32(0, 0x02014b50, true)
    dv.setUint16(4, 20, true)
    dv.setUint16(6, 20, true)
    dv.setUint16(8, 0x0800, true)
    dv.setUint16(10, 0, true)
    dv.setUint16(12, 0, true)
    dv.setUint16(14, 0x2821, true)
    dv.setUint32(16, e.crc, true)
    dv.setUint32(20, e.data.length, true)
    dv.setUint32(24, e.data.length, true)
    dv.setUint16(28, nameBytes.length, true)
    dv.setUint16(30, 0, true)
    dv.setUint16(32, 0, true)
    dv.setUint16(34, 0, true)
    dv.setUint16(36, 0, true)
    dv.setUint32(38, 0, true)
    dv.setUint32(42, e.offset, true)
    cd.set(nameBytes, 46)
    push(cd)
  }

  const end = new Uint8Array(22)
  const dve = new DataView(end.buffer)
  dve.setUint32(0, 0x06054b50, true)
  dve.setUint16(8, entries.length, true)
  dve.setUint16(10, entries.length, true)
  dve.setUint32(12, offset - cdStart, true)
  dve.setUint32(16, cdStart, true)
  push(end)

  return new Blob(chunks as BlobPart[], { type: 'application/zip' })
}
