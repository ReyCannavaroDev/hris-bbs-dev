import re

with open('scratch/nuxt/e083f2a.js', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

# Nuxt webpack manifest often contains mapping like {0: "...", 1: "..."}
chunks = re.findall(r'[\'\"\/]([a-f0-9]{7}\.js)[\'\"]', text)
print("Chunks in e083f2a.js:", len(chunks), set(chunks))

# Also search in all files for chunks
all_chunks = set()
for fname in ['scratch/nuxt/b160de4.js', 'scratch/nuxt/de4cde9.js', 'scratch/nuxt/baa0e85.js']:
    try:
        with open(fname, 'r', encoding='utf-8', errors='ignore') as f:
            t = f.read()
            found = re.findall(r'([a-f0-9]{7})\.js', t)
            all_chunks.update(found)
    except:
        pass

print("Total unique chunk hashes found:", len(all_chunks))
print(sorted(all_chunks))
