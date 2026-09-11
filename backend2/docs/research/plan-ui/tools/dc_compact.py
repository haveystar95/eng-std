#!/usr/bin/env python3
"""Compact per-frame dump: text lines with short style hints, one file per frame id."""
import re, sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import dc_extract as d

SHORT = {"font-size": "fs", "font-weight": "fw", "color": "c", "font-family": "ff", "opacity": "op",
         "background": "bg", "border": "bd", "border-radius": "r", "height": "h", "width": "w",
         "padding": "p", "margin": "m", "gap": "g", "box-shadow": "sh", "letter-spacing": "ls",
         "text-transform": "tt", "line-height": "lh", "border-bottom": "bb", "border-top": "bt"}

def st(node):
    s = node.attrs.get("style", "")
    out = []
    for p in s.split(";"):
        p = p.strip()
        if not p or ":" not in p:
            continue
        k, v = p.split(":", 1)
        k = k.strip(); v = v.strip()
        if k in SHORT:
            v = v.replace("Literata,Georgia,serif", "Literata").replace("Inter,-apple-system,'SF Pro Text',sans-serif", "Inter")
            v = v.replace("'IBM Plex Mono',monospace", "Mono")
            out.append(f"{SHORT[k]}={v}")
    return " ".join(out)

def walk(node, depth, out):
    if isinstance(node, str):
        t = re.sub(r"\s+", " ", node).strip()
        if t:
            out.append("  " * depth + "«" + t + "»")
        return
    if node.tag in ("script", "style", "helmet"):
        return
    label = node.tag
    if "id" in node.attrs:
        label += "#" + node.attrs["id"]
    if node.tag == "image-slot":
        label += f"(img src={node.attrs.get('src','')})"
    s = st(node)
    kids = node.children
    texts = [k for k in kids if isinstance(k, str)]
    elems = [k for k in kids if not isinstance(k, str)]
    if not elems and texts:
        t = re.sub(r"\s+", " ", " ".join(texts)).strip()
        out.append("  " * depth + f"[{label} {s}] «{t}»")
        return
    if not elems and not texts:
        if s:
            out.append("  " * depth + f"[{label} {s}]")
        return
    out.append("  " * depth + f"[{label} {s}]")
    for k in kids:
        walk(k, depth + 1, out)

def main():
    path, outdir = sys.argv[1], sys.argv[2]
    root = d.load(path)
    ids = sys.argv[3:]
    for fid in ids:
        n = d.find_all(root, lambda n: n.attrs.get("id") == fid)
        if not n:
            print("missing", fid); continue
        out = []
        walk(n[0], 0, out)
        with open(os.path.join(outdir, f"frame_{fid}.txt"), "w") as f:
            f.write("\n".join(out))
        print(fid, len(out))

if __name__ == "__main__":
    main()
