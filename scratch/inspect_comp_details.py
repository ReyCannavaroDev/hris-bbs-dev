import zipfile

def inspect_models(zpath, model_names):
    print("=" * 60)
    print("FILE:", zpath)
    print("=" * 60)
    with zipfile.ZipFile(zpath, 'r') as z:
        for m in z.namelist():
            for target in model_names:
                if f"/{target}/" in m.lower() and (m.endswith('.php') or m.endswith('.js')):
                    print("Found:", m)
                    content = z.read(m).decode('utf-8', errors='ignore')
                    for line in content.splitlines()[:60]:
                        if any(k in line.lower() for k in ['schema::create', 'tablename', 'table_config', 'fillable', 'joins', 'comment', 'm_comp', 'm_subcomp', 'm_dir', 'nama', 'label']):
                            print("  ", line.strip())

inspect_models(r"c:\Users\Rey Cannavaro\hris-bbs-dev\References\hris-temprina-dev-main.zip", 
               ["m_company", "m_subcomp", "m_comp", "m_dir"])

inspect_models(r"c:\Users\Rey Cannavaro\hris-bbs-dev\References\hris-msc-dev-main.zip", 
               ["m_comp", "m_dir", "m_divisi", "m_kary"])
