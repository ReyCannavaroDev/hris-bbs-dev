import urllib.request
import re
import os

scripts = [
    'b160de4.js', 'baa0e85.js', 'a8f3b26.js', 'a4def35.js', 'cddd098.js', 'a5e0309.js',
    '2c034c5.js', '4c3b3e0.js', '642b4ee.js', 'b08f04e.js', 'ceff0e9.js', '9f1b996.js',
    '50c8abc.js', '44aa7f9.js', '647686c.js', '1d577f9.js', '5a86c9f.js', 'b0024b8.js',
    '5f2823b.js', '7779fe9.js', 'a2d84d5.js', '7df08f1.js', 'de4cde9.js', '21407dd.js',
    'fbee03f.js', '05f60fd.js', 'e083f2a.js'
]

os.makedirs('scratch/nuxt', exist_ok=True)
base_url = 'https://ngopi.netlify.app/_nuxt/'

for s in scripts:
    path = os.path.join('scratch/nuxt', s)
    if not os.path.exists(path):
        try:
            urllib.request.urlretrieve(base_url + s, path)
        except Exception as e:
            print(f'Failed to fetch {s}: {e}')

print('Downloaded scripts. Now analyzing...')

for s in scripts:
    path = os.path.join('scratch/nuxt', s)
    if not os.path.exists(path):
        continue
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()

    # Search for .frontend
    if '.frontend' in content:
        print(f'\n=== FOUND .frontend in {s} ===')
        idx = 0
        while True:
            idx = content.find('.frontend', idx)
            if idx == -1:
                break
            start = max(0, idx - 150)
            end = min(len(content), idx + 200)
            print('SNIPPET:', content[start:end])
            print('-'*40)
            idx += 9

    # Search for Cores or .core
    if '.core' in content or 'Cores/' in content or 'cores/' in content:
        print(f'\n=== FOUND core/Cores in {s} ===')
        for match in re.finditer(r'([^\n\r;]{0,100}(?:Cores/|\.core|addCore|createCore)[^\n\r;]{0,100})', content):
            print('CORE SNIPPET:', match.group(0))
