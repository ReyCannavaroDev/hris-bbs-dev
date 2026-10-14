import os
import re

dir_path = 'scratch/nuxt'
files = os.listdir(dir_path)

for f_name in files:
    full_path = os.path.join(dir_path, f_name)
    with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
        text = f.read()

    # Search for URL endpoints (like /api/ or similar)
    endpoints = set(re.findall(r'[\'\"](/[a-zA-Z0-9_\-\/]+)[\'\"]', text))
    interesting = [e for e in endpoints if any(k in e.lower() for k in ['model', 'generate', 'create', 'file', 'project', 'core', 'artisan', 'make'])]
    if interesting:
        print(f"\n[{f_name}] Interesting endpoints:")
        for ep in sorted(interesting)[:15]:
            print("  ", ep)

    # Search for '.frontend' with case-insensitive
    if '.frontend' in text.lower():
        print(f"\n[{f_name}] FOUND .frontend!")
        for m in re.finditer(r'([^\s;,\'\"]{0,50}\.frontend[^\s;,\'\"]{0,50})', text, re.I):
            print("  ", m.group(0))

    # Search for 'Search...' or placeholder
    for m in re.finditer(r'([^\n\r;]{0,50}Search[^\n\r;]{0,50})', text):
        print(f"[{f_name}] Search match:", m.group(0))
