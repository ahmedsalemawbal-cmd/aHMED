"""Builds the optimized web images in public/assets from the approved design files.

Usage:  python3 tools/optimize-images.py <path-to-svg-country-flags/svg>
Needs Pillow. Run it again whenever a source image in design/assets changes.
"""
import os
import re
import shutil
import sys

from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "design", "assets")
OUT = os.path.join(ROOT, "public", "assets")
IMG = os.path.join(OUT, "img")
os.makedirs(IMG, exist_ok=True)


def save_webp(im, name, width=None, height=None, quality=80):
    if width and im.width > width:
        im = im.resize((width, round(im.height * width / im.width)), Image.LANCZOS)
    if height and im.height > height:
        im = im.resize((round(im.width * height / im.height), height), Image.LANCZOS)
    path = os.path.join(IMG, name)
    im.save(path, "WEBP", quality=quality, method=6)
    print(f"{name:28} {im.width}x{im.height}  {os.path.getsize(path) // 1024} KB")


# Hero photos: displayed up to 460px wide inside the arch, so 848w covers 2x screens.
for n in ("hero-1", "hero-2"):
    im = Image.open(os.path.join(SRC, n + ".jpg")).convert("RGB")
    save_webp(im, n + ".webp", width=848)
    save_webp(im, n + "-480.webp", width=480)
save_webp(Image.open(os.path.join(SRC, "images", "hero-3.webp")).convert("RGB"), "hero-3-480.webp", width=480)

# hero-3, the about photo and the nine profession photos are already small WebP files: copy them as-is.
for n in ["hero-3", "about-team"] + [f"job-{i}" for i in range(1, 10)]:
    shutil.copyfile(os.path.join(SRC, "images", n + ".webp"), os.path.join(IMG, n + ".webp"))

# Logos keep their original transparent margin so they render at exactly the design's size.
for color in ("gold", "white"):
    im = Image.open(os.path.join(SRC, f"logo-{color}.png")).convert("RGBA")
    if color == "gold":
        save_webp(im, "logo-gold-192.webp", height=192, quality=90)
        save_webp(im, "logo-gold-840.webp", height=840, quality=88)
    else:
        save_webp(im, "logo-white-480.webp", height=480, quality=85)

# Favicons and the social-share image use the gold logo on the brand petrol colour.
gold = Image.open(os.path.join(SRC, "logo-gold.png")).convert("RGBA")
gold = gold.crop(gold.getbbox())
PETROL = (11, 79, 108, 255)


def on_square(size, pad, bg):
    canvas = Image.new("RGBA", (size, size), bg)
    h = size - 2 * pad
    w = round(gold.width * h / gold.height)
    canvas.alpha_composite(gold.resize((w, h), Image.LANCZOS), ((size - w) // 2, pad))
    return canvas


on_square(32, 1, (0, 0, 0, 0)).save(os.path.join(OUT, "icons", "favicon-32.png"))
on_square(180, 22, PETROL).convert("RGB").save(os.path.join(OUT, "icons", "apple-touch-icon.png"))
on_square(192, 24, PETROL).save(os.path.join(OUT, "icons", "icon-192.png"))
on_square(512, 64, PETROL).save(os.path.join(OUT, "icons", "icon-512.png"))

og = Image.new("RGBA", (1200, 630), PETROL)
frame = Image.new("RGBA", og.size, (0, 0, 0, 0))
from PIL import ImageDraw  # noqa: E402

ImageDraw.Draw(frame).rounded_rectangle((24, 24, 1176, 606), radius=28, outline=(188, 162, 78, 90), width=2)
og.alpha_composite(frame)
lh = 440
lw = round(gold.width * lh / gold.height)
og.alpha_composite(gold.resize((lw, lh), Image.LANCZOS), ((1200 - lw) // 2, (630 - lh) // 2))
og.convert("RGB").save(os.path.join(OUT, "img", "og-image.jpg"), quality=86)
print("icons + og-image written")

# Country flags (official aspect ratios) — copied as SVG with explicit width/height.
flags_src = sys.argv[1] if len(sys.argv) > 1 else None
if flags_src:
    os.makedirs(os.path.join(OUT, "flags"), exist_ok=True)
    for cc in ("ph", "ke", "ug", "et", "bi", "gh", "bd", "in", "lk"):
        svg = open(os.path.join(flags_src, cc + ".svg"), encoding="utf8").read()
        head = re.search(r"<svg[^>]*>", svg).group(0)
        if "width=" not in head:
            vb = [float(v) for v in re.search(r'viewBox="([^"]+)"', head).group(1).split()]
            svg = svg.replace(head, head[:-1] + f' width="{vb[2]:g}" height="{vb[3]:g}">', 1)
        open(os.path.join(OUT, "flags", cc + ".svg"), "w", encoding="utf8").write(svg)
    print("flags written")
