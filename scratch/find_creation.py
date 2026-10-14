import os
import re

dir_path = 'scratch/nuxt'
files = os.listdir(dir_path)

print(f"Total files: {len(files)}")

keywords = ['frontend', 'migrate', 'sidebar', 'createModel', 'addModel', 'createFile', 'addFile', 'Search...', 'cores', 'Cores']

for f_name in files:
    full_path = os.path.join(dir_path, f_name)
    with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
        text = f.read()
    
    # Check for keywords
    found = [k for k in keywords if k in text]
    if 'Search...' in text:
        print(f"\n>>> FOUND 'Search...' in {f_name} (Size: {len(text)})")
    if 'migrate' in text:
        print(f"\n>>> FOUND 'migrate' in {f_name}")

    # Search for input / prompt / modal where user enters name
    # e.g. prompt( or modal or + button click
    for m in re.finditer(r'([^\n\r;]{0,100}(?:prompt\(|newModel|createModel|\.frontend|artisan)[^\n\r;]{0,100})', text):
        print(f"[{f_name}] {m.group(0)}")
