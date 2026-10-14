import urllib.request
import re
import os

with open('scratch/nuxt/b160de4.js', 'r', encoding='utf-8') as f:
    text = f.read()

# mapping
items = re.findall(r'(\d+):"([a-f0-9]{7})"', text)
mapping = {int(k): v for k, v in items}

with open('scratch/nuxt/b0024b8.js', 'r', encoding='utf-8') as f:
    t = f.read()

idx = t.find('path:"/p/:id"')
sub = t[idx:idx+1500]
chunk_ids = [int(x) for x in re.findall(r'n\.e\((\d+)\)', sub)]

print(f"Total chunk IDs for /p/:id: {len(chunk_ids)}")

found_matches = []

for cid in chunk_ids:
    if cid in mapping:
        h = mapping[cid]
        cname = f"{h}.js"
        cpath = os.path.join('scratch/nuxt', cname)
        if not os.path.exists(cpath):
            try:
                urllib.request.urlretrieve(f'https://ngopi.netlify.app/_nuxt/{cname}', cpath)
            except Exception as e:
                print(f"Err {cname}: {e}")
                continue
        with open(cpath, 'r', encoding='utf-8', errors='ignore') as fp:
            content = fp.read()
            if 'Search...' in content or '.frontend' in content or 'Cores' in content or 'addModel' in content or 'prompt(' in content:
                print(f"\n>>> FOUND KEYWORDS IN {cname} (cid: {cid}, size: {len(content)})")
                for m in re.finditer(r'([^\n\r;]{0,80}(?:Search\.\.\.|\.frontend|prompt\(|addModel|createModel)[^\n\r;]{0,80})', content):
                    print("  ", m.group(0))
