with open('Models/m_jam_kerja/Custom.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_snippet = """        $jamKerja = request('filter_jam_kerja') ?? request('jam_kerja');
        if (!empty($jamKerja)) {
            $val = trim((string)$jamKerja);
            $model = $model->where(function ($q) use ($val) {"""

new_snippet = """        $jamKerja = request('filter_jam_kerja') ?? request('jam_kerja');
        if (!empty($jamKerja)) {
            $val = trim((string)$jamKerja);
            $val = str_replace('.', ':', $val);
            $model = $model->where(function ($q) use ($val) {"""

# Replace CRLF and LF variations
content_normalized = content.replace('\r\n', '\n')
if old_snippet in content_normalized:
    updated = content_normalized.replace(old_snippet, new_snippet, 1)
    with open('Models/m_jam_kerja/Custom.php', 'w', encoding='utf-8', newline='\n') as f:
        f.write(updated)
    print("SUCCESS: Updated Custom.php")
else:
    print("FAILED: old_snippet not found")

