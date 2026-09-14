#!/usr/bin/env python3
"""A synthetic «dialogue in one sound» for tuning PcmTurnCutter before the vendor answers (DAY-UI-3).

  synth_dialogue.py OUT_DIR

macOS `say` reads every line with one of two voices (female / male, like the scene's cast), the
lines are joined with pauses of 280–700 ms, and a sentence inside a line keeps its own pause — the
trap a «longest pauses» cut falls into. Writes `dialogue.pcm` (16-bit LE mono 24 kHz), `dialogue.json`
(texts + where every line really starts, in ms) and the same for a one-voice `batch` of words.
"""
import json
import os
import random
import struct
import subprocess
import sys
import tempfile
import wave

RATE = 24000
out = sys.argv[1]
os.makedirs(out, exist_ok=True)
random.seed(7)

DIALOGUE = [
    ('F', 'Hello, please have a seat. What brings you in today?'),
    ('M', 'My lower back hurts.'),
    ('F', 'I see. How long have you had this pain?'),
    ('M', 'For about a week.'),
    ('F', 'Is it worse when you move? Or when you sit for a long time?'),
    ('M', 'Yes, when I bend down.'),
    ('F', 'Have you taken anything for it?'),
    ('M', 'Only ibuprofen, twice a day.'),
    ('F', 'Okay. I will prescribe a painkiller. Take it after meals.'),
    ('M', 'Thank you, doctor.'),
    ('F', 'Do not lift anything heavy this week.'),
    ('M', 'Should I come back for a check-up?'),
    ('F', 'Yes, come back in ten days. We will see how you feel.'),
    ('M', 'Thanks. See you then.'),
]
WORDS = ['appointment', 'lower back', 'prescription', 'side effect', 'symptoms', 'painkiller', 'referral', 'dizzy']
VOICES = {'F': 'Samantha', 'M': 'Daniel'}


def say(text, voice):
    with tempfile.TemporaryDirectory() as tmp:
        path = os.path.join(tmp, 'line.wav')
        subprocess.run(['say', '-v', voice, '-o', path, f'--data-format=LEI16@{RATE}', text], check=True)
        with wave.open(path) as w:
            return w.readframes(w.getnframes())


def silence(ms):
    return b'\x00\x00' * int(RATE * ms / 1000)


def build(name, lines, gap_range):
    pcm = b''
    starts = []
    for i, (who, text) in enumerate(lines):
        if i > 0:
            pcm += silence(random.randint(*gap_range))
        starts.append(round(len(pcm) / 2 / RATE * 1000))
        pcm += say(text, VOICES[who])
    with open(os.path.join(out, f'{name}.pcm'), 'wb') as f:
        f.write(pcm)
    with open(os.path.join(out, f'{name}.json'), 'w') as f:
        json.dump({'rate': RATE, 'texts': [t for _, t in lines], 'starts_ms': starts}, f, indent=1)
    print(name, len(lines), 'lines', round(len(pcm) / 2 / RATE, 1), 's')


build('dialogue', DIALOGUE, (280, 700))
build('batch', [('M', w) for w in WORDS], (600, 1100))
