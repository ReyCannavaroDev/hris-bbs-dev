import zipfile
import re

references_dir = r"c:\Users\Rey Cannavaro\hris-bbs-dev\References"

def inspect_zip(zname, check_files):
    zpath = f"{references_dir}\\{zname}"
    print("=" * 70)
    print(f"ANAYLZING: {zname}")
    print("=" * 70)
    with zipfile.ZipFile(zpath, 'r') as z:
        for fname in check_files:
            matches = [n for n in z.namelist() if fname.lower() in n.lower()]
            for m in matches:
                if any(ext in m for ext in ['.php', '.js', '.blade.php']) and not m.endswith('/'):
                    print(f"--- File: {m} ---")
                    try:
                        content = z.read(m).decode('utf-8', errors='ignore')
                        # Print relevant lines (schema, fillable, joins, where comp)
                        lines = content.splitlines()
                        for i, line in enumerate(lines[:100]):
                            if any(k in line.lower() for k in ['schema::create', 'm_comp', 'm_dir', 'm_subcomp', 'm_divisi', 'm_dept', 'sbu', 'cabang']):
                                print(f"  L{i+1}: {line.strip()}")
                    except Exception as e:
                        print("  Error reading:", e)

# 1. Temprina
inspect_zip("hris-temprina-dev-main.zip", [
    "m_comp/migrate.php", "m_comp/Migration.php",
    "m_company/migrate.php", "m_subcomp/migrate.php",
    "m_dir/migrate.php", "m_dir/Migration.php",
    "m_kary/migrate.php", "m_kary/Migration.php",
    "t_final_gaji/migrate.php", "t_final_gaji/Migration.php"
])

# 2. MSC
inspect_zip("hris-msc-dev-main.zip", [
    "m_comp/migrate.php", "m_dir/migrate.php",
    "m_kary/migrate.php", "t_final_gaji/migrate.php",
    "t_perhitungan_gaji/migrate.php"
])
