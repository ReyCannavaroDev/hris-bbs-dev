import json
from collections import defaultdict

with open('DataResponse/m_menu.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

items = data.get('data', [])

print(f"Total menu records: {len(items)}")

# Group by modul
by_modul = defaultdict(list)
for it in items:
    by_modul[it.get('modul')].append(it)

for mod, m_items in sorted(by_modul.items(), key=lambda x: str(x[0])):
    print(f"\n=== Modul: {mod} ({len(m_items)} menus) ===")
    for m in sorted(m_items, key=lambda x: (x.get('sequence') or 999)):
        print(f"  [{m.get('id')}] seq: {m.get('sequence')} | menu: {m.get('menu')} | path: {m.get('path')} | endpoint: {m.get('endpoint')} | active: {m.get('is_active')}")

