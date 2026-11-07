import re, subprocess

with open('Javascript/m_respo.js', 'r', encoding='utf-8') as f:
    js_content = f.read()

pattern = r'//\s*@if\(\s*\$id\s*\)(.*?)//\s*@else[^\n]*\n(.*?)//\s*@endif[^\n]*'
match = re.search(pattern, js_content, re.DOTALL)
print("Matched?", bool(match))

if match:
    # Landing (id is None)
    landing_js = js_content[:match.start()] + match.group(2) + js_content[match.end():]
    with open('scratch/test_landing.js', 'w', encoding='utf-8') as f:
        f.write(landing_js)
    res1 = subprocess.run(['node', '-c', 'scratch/test_landing.js'], capture_output=True, text=True)
    print('Landing syntax exit code:', res1.returncode, res1.stderr)

    # Form (id is set)
    form_js = js_content[:match.start()] + match.group(1) + js_content[match.end():]
    with open('scratch/test_form.js', 'w', encoding='utf-8') as f:
        f.write(form_js)
    res2 = subprocess.run(['node', '-c', 'scratch/test_form.js'], capture_output=True, text=True)
    print('Form syntax exit code:', res2.returncode, res2.stderr)
