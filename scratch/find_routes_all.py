import os
import re

for f in os.listdir('scratch/nuxt'):
    p = os.path.join('scratch/nuxt', f)
    with open(p, 'r', encoding='utf-8', errors='ignore') as fp:
        t = fp.read()
        if 'path:"/p' in t or 'path:"/' in t:
            print(f"Found route in {f}")
            for m in re.finditer(r'path:"([^"]+)"', t):
                print(' ', m.group(0))
