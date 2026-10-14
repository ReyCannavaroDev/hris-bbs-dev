with open('scratch/nuxt/b0024b8.js', 'r', encoding='utf-8') as f:
    t = f.read()

idx = t.find('path:"/p/:id"')
print(t[idx+900:idx+1500])
