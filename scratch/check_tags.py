import re

with open('Blades/m_respo.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Check tags
tags = re.findall(r'<\/?([a-zA-Z0-9_-]+)[^>]*\/?>', content)
stack = []
self_closing = ['input', 'img', 'br', 'hr', 'icon']

for tag_match in re.finditer(r'<(\/)?([a-zA-Z0-9_-]+)([^>]*?)(\/)?>', content):
    is_closing = bool(tag_match.group(1))
    tag_name = tag_match.group(2)
    is_self = bool(tag_match.group(4)) or tag_name in self_closing
    
    if is_self and not is_closing:
        continue
    if not is_closing:
        stack.append((tag_name, tag_match.start()))
    else:
        if not stack:
            print(f"Extra closing tag </{tag_name}> at {tag_match.start()}")
        else:
            top, pos = stack.pop()
            if top != tag_name:
                print(f"Mismatched tag: expected </{top}> (opened at {pos}), found </{tag_name}> at {tag_match.start()}")

if stack:
    for top, pos in stack:
        print(f"Unclosed tag <{top}> opened at {pos}")
else:
    print("All tags matched!")

