#!/usr/bin/env python3
"""Extract artboards + captions from a Claude Design .dc.html canvas into readable text.

Usage: dc_extract.py <file.html> <mode> [args]
  modes:
    sections            - list top-level <section> headers
    section <title>     - dump a whole section as indented text (styles summarized)
    frame <id>          - dump the block with that id (and following sibling captions)
    ids                 - list ids
"""
import re
import sys
from html.parser import HTMLParser

STYLE_KEYS = (
    "font-size", "font-weight", "font-family", "color", "background", "border", "border-radius",
    "padding", "margin", "gap", "width", "height", "opacity", "letter-spacing", "line-height",
    "text-transform", "box-shadow", "border-bottom", "border-top", "display", "flex", "align-items",
    "justify-content", "position", "top", "left", "right", "bottom", "transform", "overflow",
)


class Node:
    def __init__(self, tag, attrs, parent=None):
        self.tag = tag
        self.attrs = dict(attrs)
        self.children = []
        self.parent = parent

    def text(self):
        out = []
        for c in self.children:
            if isinstance(c, str):
                out.append(c)
            else:
                out.append(c.text())
        return "".join(out)


VOID = {"br", "img", "meta", "link", "input", "hr", "image-slot"}


class P(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.root = Node("root", [])
        self.cur = self.root

    def handle_starttag(self, tag, attrs):
        n = Node(tag, attrs, self.cur)
        self.cur.children.append(n)
        if tag not in VOID and tag != "image-slot":
            self.cur = n
        elif tag == "image-slot":
            self.cur = n

    def handle_startendtag(self, tag, attrs):
        n = Node(tag, attrs, self.cur)
        self.cur.children.append(n)

    def handle_endtag(self, tag):
        n = self.cur
        while n is not None and n.tag != tag:
            n = n.parent
        if n is not None and n.parent is not None:
            self.cur = n.parent

    def handle_data(self, data):
        if data.strip():
            self.cur.children.append(data)


def style_summary(style, full=False):
    if not style:
        return ""
    parts = [p.strip() for p in style.split(";") if p.strip()]
    keep = []
    for p in parts:
        k = p.split(":", 1)[0].strip()
        if full or k in STYLE_KEYS:
            keep.append(p)
    return "; ".join(keep)


def dump(node, depth=0, out=None, full=False, maxdepth=60):
    if out is None:
        out = []
    if isinstance(node, str):
        t = re.sub(r"\s+", " ", node).strip()
        if t:
            out.append("  " * depth + "«" + t + "»")
        return out
    if node.tag in ("script", "style", "helmet"):
        return out
    attrs = []
    if "id" in node.attrs:
        attrs.append("#" + node.attrs["id"])
    for k in ("data-screen-label", "src", "shape", "placeholder", "aria-label", "title"):
        if k in node.attrs:
            attrs.append(f"{k}={node.attrs[k]!r}")
    st = style_summary(node.attrs.get("style", ""), full)
    # collapse: if node has only one text child, print inline
    kids = node.children
    if len(kids) == 1 and isinstance(kids[0], str):
        t = re.sub(r"\s+", " ", kids[0]).strip()
        out.append("  " * depth + f"<{node.tag} {' '.join(attrs)} [{st}]> «{t}»")
        return out
    out.append("  " * depth + f"<{node.tag} {' '.join(attrs)} [{st}]>")
    if depth < maxdepth:
        for c in kids:
            dump(c, depth + 1, out, full, maxdepth)
    return out


def find_all(node, pred, acc=None):
    if acc is None:
        acc = []
    if isinstance(node, str):
        return acc
    if pred(node):
        acc.append(node)
    for c in node.children:
        find_all(c, pred, acc)
    return acc


def load(path):
    p = P()
    p.feed(open(path, encoding="utf-8").read())
    return p.root


def main():
    path, mode = sys.argv[1], sys.argv[2]
    root = load(path)
    full = "--full" in sys.argv
    if mode == "sections":
        for s in find_all(root, lambda n: n.tag == "section"):
            heads = find_all(s, lambda n: "text-transform:uppercase" in n.attrs.get("style", "") and "color:#8A857E" in n.attrs.get("style", ""))
            t = heads[0].text().strip() if heads else "?"
            titles = [c for c in s.children if not isinstance(c, str)]
            sub = ""
            if titles:
                big = find_all(s, lambda n: "font-size:26px" in n.attrs.get("style", ""))
                if big:
                    sub = big[0].text().strip()
            print(f"SECTION: {t} — {sub}")
    elif mode == "ids":
        for n in find_all(root, lambda n: "id" in n.attrs):
            print(n.attrs["id"], n.tag)
    elif mode == "section":
        title = sys.argv[3]
        for s in find_all(root, lambda n: n.tag == "section"):
            heads = find_all(s, lambda n: "text-transform:uppercase" in n.attrs.get("style", "") and "color:#8A857E" in n.attrs.get("style", ""))
            t = heads[0].text().strip() if heads else "?"
            if t == title:
                print("\n".join(dump(s, 0, None, full)))
    elif mode == "frame":
        fid = sys.argv[3]
        n = find_all(root, lambda n: n.attrs.get("id") == fid)
        if not n:
            print("not found")
            return
        print("\n".join(dump(n[0], 0, None, full)))
    elif mode == "frametext":
        fid = sys.argv[3]
        n = find_all(root, lambda n: n.attrs.get("id") == fid)
        if not n:
            print("not found")
            return
        # print only text lines
        for line in dump(n[0], 0, None, full):
            if "«" in line:
                print(line)
    elif mode == "texttable":
        # find tables
        for t in find_all(root, lambda n: n.tag == "table"):
            for tr in find_all(t, lambda n: n.tag == "tr"):
                cells = [re.sub(r"\s+", " ", c.text()).strip() for c in tr.children if not isinstance(c, str)]
                print(" | ".join(cells))
            print("-----")


if __name__ == "__main__":
    main()
