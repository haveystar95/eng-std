"""
FIX-3 §2 — THE PRICE LIST MEASURED ON THE PHONE.

Input `../live/answers.tsv`: every answered card of the live server (`wordtrainer`, read-only session, 22.09), one per
line — day id, kind, stage, answered_at (unix s), position, rounds of «Скажи целиком», scenes of the «Вспомнить» sheet,
card id — in answering order within a day.

The rule of the наряд: the seconds a card took are the gap from the day's previous answer; a gap over two minutes is the
learner away and is left out; the first answer of a day has no gap. «Скажи целиком» is priced per round and the sheet
per scene. The new price of a kind is its median × 1.3, rounded to 5 s (at least 5); a kind with no answers takes its old
price × the ratio new/old of the measured kinds, weighted by their answers.

Run from `../live`: `python3 ../tools/prices.py > prices.txt` (it also writes `new_pace.json`, the list for the config).
"""
import csv, statistics, math, json, sys
OLD = {'word_intro':8,'word_repeat':12,'word_choose':10,'word_listen':10,'word_assemble':20,'word_in_line':10,'phrase_intro':12,'phrase_assemble':25,'phrase_choose_back':12,'phrase_slot':12,'phrase_slot_listen':12,'phrase_repeat':25,'phrase_other_slot':25,'phrase_combine':20,'dialogue_partner':15,'dialogue_answer':30,'dialogue_ask':45,'dialogue_rescue':15,'listen_dialogue':110,'listen_question':12,'listen_review':30,'listen_predict':15,'listen_pace':25,'listen_number':15,'speak_answer':35,'speak_echo':25,'speak_retell':30,'recall_scenes':60}
MAX_GAP=120
rows=[r for r in csv.reader(open('answers.tsv'), delimiter='\t')]
per={}
prev_day=None; prev_t=None
for day,kind,stage,t,pos,rounds,scenes,cid in rows:
    t=int(t); rounds=int(rounds); scenes=int(scenes)
    if day!=prev_day:
        prev_day=day; prev_t=t; continue   # first answer of the day: no gap
    gap=t-prev_t; prev_t=t
    if gap>MAX_GAP: continue
    unit = gap
    if kind=='phrase_other_slot': unit = gap/max(1,rounds)
    if kind=='recall_scenes': unit = gap/max(1,scenes)
    per.setdefault(kind,[]).append(unit)
def r5(x): return max(5, int(5*round(x/5)))
new={}; n={}
for k,v in per.items():
    med=statistics.median(v); new[k]=r5(med*1.3); n[k]=(len(v),med)
# kinds without data: proportional to old prices
have=[k for k in new if k in OLD]
ratio = sum(new[k]*n[k][0] for k in have)/sum(OLD[k]*n[k][0] for k in have)
out={}
for k,old in OLD.items():
    if k in new: out[k]=(old,new[k],n[k][0],round(n[k][1],1),'data')
    else: out[k]=(old,r5(old*ratio),0,None,'ratio')
print('weighted ratio new/old over kinds with data: %.3f'%ratio)
for k,(old,nw,cnt,med,src) in out.items(): print(f'{k:20s} old {old:4d} new {nw:4d} n={cnt:4d} median={med} {src}')
extra=[k for k in per if k not in OLD]
print('kinds in data not in the price list:', extra)
json.dump({k:v[1] for k,v in out.items()}, open('new_pace.json','w'))
