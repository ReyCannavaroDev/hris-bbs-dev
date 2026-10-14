with open('scratch/nuxt/b0024b8.js', 'r', encoding='utf-8') as f:
    t = f.read()

idx = t.find('/p/:id')
print(t[idx-100:idx+300])
