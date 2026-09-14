#!/usr/bin/env python3
"""Canvas reader: element tree with ALL styles and text.

  dc.py FILE tree  SELECTOR   -> indented tree (tag, all style props, attrs, text) of the element
  dc.py FILE text  SELECTOR   -> plain text of the element (block-separated)
  dc.py FILE lines A B        -> text of source lines A..B (tables flattened)
SELECTOR: id=XXX or label=XXX (data-screen-label)
"""
import sys
from html.parser import HTMLParser

VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}


class Node:
    def __init__(self, tag, attrs, parent):
        self.tag = tag
        self.attrs = dict(attrs)
        self.parent = parent
        self.children = []  # Node or str


class P(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.root = Node('#root', [], None)
        self.cur = self.root

    def handle_starttag(self, tag, attrs):
        n = Node(tag, attrs, self.cur)
        self.cur.children.append(n)
        if tag not in VOID:
            self.cur = n

    def handle_startendtag(self, tag, attrs):
        n = Node(tag, attrs, self.cur)
        self.cur.children.append(n)

    def handle_endtag(self, tag):
        if tag in VOID:
            return
        c = self.cur
        while c is not None and c.tag != tag:
            c = c.parent
        if c is not None and c.parent is not None:
            self.cur = c.parent

    def handle_data(self, data):
        if data.strip():
            self.cur.children.append(data)


def find(node, key, val):
    if isinstance(node, str):
        return None
    if node.attrs.get(key) == val:
        return node
    for ch in node.children:
        r = find(ch, key, val)
        if r is not None:
            return r
    return None


SKIP_TAGS = {'script', 'style'}


def dump(node, depth=0, out=None):
    ind = '  ' * depth
    if isinstance(node, str):
        out.append(f'{ind}"{node.strip()}"')
        return
    if node.tag in SKIP_TAGS:
        return
    a = dict(node.attrs)
    style = a.pop('style', '')
    extra = ' '.join(f'{k}="{v}"' for k, v in a.items() if k not in ('xmlns',))
    if node.tag in ('svg',):
        # compact svg: one line with attrs + child tags summary
        kids = []
        def walk(n):
            for c in n.children:
                if isinstance(c, str):
                    kids.append('"' + c.strip() + '"')
                else:
                    kids.append(c.tag + '(' + ' '.join(f'{k}={v}' for k, v in c.attrs.items()) + ')')
                    walk(c)
        walk(node)
        out.append(f'{ind}<svg {extra} style[{style}]> ' + ' | '.join(kids)[:600])
        return
    out.append(f'{ind}<{node.tag}{(" " + extra) if extra else ""}> {{{style}}}')
    for ch in node.children:
        dump(ch, depth + 1, out)


def text(node, out):
    if isinstance(node, str):
        out.append(node.strip())
        return
    if node.tag in SKIP_TAGS:
        return
    block = node.tag in ('div', 'p', 'li', 'tr', 'h1', 'h2', 'h3', 'br', 'section')
    if block:
        out.append('\n')
    for ch in node.children:
        text(ch, out)
    if block:
        out.append('\n')


def main():
    f = sys.argv[1]
    mode = sys.argv[2]
    src = open(f, encoding='utf-8').read()
    if mode == 'lines':
        a, b = int(sys.argv[3]), int(sys.argv[4])
        chunk = '\n'.join(src.split('\n')[a - 1:b])
        p = P()
        p.feed(chunk)
        out = []
        text(p.root, out)
        s = ' '.join(out)
        import re
        s = re.sub(r'[ \t]*\n[ \t\n]*', '\n', s)
        print(s)
        return
    p = P()
    p.feed(src)
    if mode == 'many':
        import os
        outdir = sys.argv[3]
        os.makedirs(outdir, exist_ok=True)
        for label in sys.argv[4:]:
            n = find(p.root, 'data-screen-label', label)
            if n is None:
                print('not found', label)
                continue
            out = []
            dump(n, 0, out)
            name = label.replace(' · ', '-').replace(' ', '-')
            with open(os.path.join(outdir, name + '.txt'), 'w') as fh:
                fh.write('\n'.join(out))
            print(name, sum(len(x) for x in out))
        return
    sel = sys.argv[3]
    k, v = sel.split('=', 1)
    key = 'data-screen-label' if k == 'label' else k
    n = find(p.root, key, v)
    if n is None:
        print('not found', sel)
        sys.exit(1)
    out = []
    if mode == 'tree':
        dump(n, 0, out)
        print('\n'.join(out))
    else:
        text(n, out)
        import re
        s = ' '.join(out)
        s = re.sub(r'[ \t]*\n[ \t\n]*', '\n', s)
        print(s)


main()
