import json

path = r'DataResponse/m_menu.json'
with open(path, 'r', encoding='utf-8') as f:
    data = json.load(f)

print("Type of data:", type(data))
if isinstance(data, dict):
    print("Keys:", data.keys())
    items = data.get('data', data)
elif isinstance(data, list):
    items = data
else:
    items = []

print("Total items:", len(items))
if items:
    print("First item keys:", list(items[0].keys()))
    print("First item sample:", items[0])

