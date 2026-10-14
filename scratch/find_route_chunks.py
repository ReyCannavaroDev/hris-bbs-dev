import urllib.request
import re
import os

with open('scratch/nuxt/b160de4.js', 'r', encoding='utf-8') as f:
    text = f.read()

# Extract chunk mapping: {0:"b723093",...}
mapping_match = re.search(r'return n\.p\+""\+(\{[^}]+\})\[e\]\+"\.js"', text)
if not mapping_match:
    # Try finding the dict
    mapping_match = re.search(r'(\{\d+:"[a-f0-9]{7}"[^\}]+\})', text)

chunks_dict = {}
if mapping_match:
    items = re.findall(r'(\d+):"([a-f0-9]{7})"', mapping_match.group(0))
    for k, v in items:
        chunks_dict[int(k)] = v

print(f"Total mapped chunks: {len(chunks_dict)}")

# Let's search inside the app router to find which chunk is /p/:id
# Usually in e083f2a.js or similar
with open('scratch/nuxt/e083f2a.js', 'r', encoding='utf-8', errors='ignore') as f:
    app_text = f.read()

# Look for path: "/p/" or name: "p-id"
routes = re.findall(r'path:"([^"]+)"[^}]+component:[^}]+', app_text)
print("Routes:", routes)

# Let's search for chunk IDs associated with routes
route_matches = re.findall(r'path:"/p[/:]([^"]*)",component:function\(\)\{return [^}]+\.e\((\d+)\)', app_text)
print("Route matches for /p:", route_matches)

# Download chunks that might be the main editor
# Let's download all chunks or check chunks referenced in /p
target_chunks = [v for k, v in chunks_dict.items()]
print(f"Found {len(target_chunks)} chunk hashes")

# Let's search for the ones related to p-id
p_chunks = re.findall(r'e\((\d+)\)', app_text)
print("Chunks called in app:", set(p_chunks))

for cid in set(p_chunks):
    cid = int(cid)
    if cid in chunks_dict:
        h = chunks_dict[cid]
        cname = f"{h}.js"
        cpath = os.path.join('scratch/nuxt', cname)
        if not os.path.exists(cpath):
            try:
                urllib.request.urlretrieve(f'https://ngopi.netlify.app/_nuxt/{cname}', cpath)
                print(f"Downloaded route chunk {cname}")
            except Exception as e:
                print(f"Error {cname}: {e}")
