import re

with open('scratch/nuxt/b160de4.js', 'r', encoding='utf-8') as f:
    t = f.read()

m = re.search(r'453:"([a-f0-9]{7})"', t)
if m:
    print('Chunk 453:', m.group(1))
