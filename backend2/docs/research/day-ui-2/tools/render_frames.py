#!/usr/bin/env python3
"""Render plan-canvas frames to PNG, 390 × 844 @2x, with headless Chrome (DAY-UI-2).

  python3 render_frames.py OUT_DIR '23-0a' '23-0b · прокручено' …

A frame is named by its `data-screen-label`. File access between file:// pages needs
`--allow-file-access-from-files`; the canvas's own scripts and photos are not in the repository, so
photo slots render as their tone — the frame is for the composition, not for the pictures.
"""
import os
import subprocess
import sys
import urllib.parse

CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
HERE = os.path.dirname(os.path.abspath(__file__))
out = sys.argv[1]
os.makedirs(out, exist_ok=True)
for label in sys.argv[2:]:
    name = label.replace(' · ', '-').replace(' ', '-')
    url = f'file://{HERE}/frame.html#' + urllib.parse.quote(label)
    subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--allow-file-access-from-files', '--hide-scrollbars',
                    '--force-device-scale-factor=2', '--window-size=390,844', '--virtual-time-budget=8000',
                    f'--screenshot={out}/{name}.png', url], capture_output=True, check=False)
    print('rendered', name)
