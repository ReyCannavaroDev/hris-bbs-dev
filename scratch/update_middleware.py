with open('Cores/Middleware.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_snippet = """        // Normalisasi filter_jam_kerja: pertahankan parameter agar diproses oleh scopeFilter di Custom.php
        if (isset($requestData['filter_jam_kerja']) && $modelInstance && in_array('waktu_mulai', $modelInstance->columns ?? [])) {
            $val = trim((string)$requestData['filter_jam_kerja']);
            if ($val !== '') {"""

new_snippet = """        // Normalisasi filter_jam_kerja: pertahankan parameter agar diproses oleh scopeFilter di Custom.php
        if (isset($requestData['filter_jam_kerja']) && $modelInstance && in_array('waktu_mulai', $modelInstance->columns ?? [])) {
            $val = trim((string)$requestData['filter_jam_kerja']);
            $val = str_replace('.', ':', $val);
            if ($val !== '') {"""

content_normalized = content.replace('\r\n', '\n')
if old_snippet in content_normalized:
    updated = content_normalized.replace(old_snippet, new_snippet, 1)
    with open('Cores/Middleware.php', 'w', encoding='utf-8', newline='\n') as f:
        f.write(updated)
    print("SUCCESS: Updated Middleware.php")
else:
    print("FAILED: old_snippet not found")

