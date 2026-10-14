with open('scratch/nuxt/fe7e2ac.js', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

print("Length of fe7e2ac.js:", len(text))

# Search for commands, plus button, modal, prompt, inputs
import re
print("Matches for Search or prompt:")
for m in re.finditer(r'([^\n\r;]{0,100}(?:Search|prompt|create|addModel|frontend|cores|Cores|\.php|\+)[^\n\r;]{0,100})', text[:10000]):
    print("  ", m.group(0)[:120])
