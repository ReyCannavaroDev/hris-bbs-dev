import zipfile
import os

references_dir = r"c:\Users\Rey Cannavaro\hris-bbs-dev\References"
zips = [
    "hris-budi-jaya-dev-main.zip",
    "hris-kilang-dev-main.zip",
    "hris-msc-dev-main.zip",
    "hris-temprina-dev-main.zip"
]

for zname in zips:
    zpath = os.path.join(references_dir, zname)
    print("=" * 60)
    print(f"INSPECTING: {zname}")
    print("=" * 60)
    with zipfile.ZipFile(zpath, 'r') as z:
        namelist = z.namelist()
        # Find all Models
        model_dirs = set()
        for name in namelist:
            parts = name.split('/')
            if 'Models' in parts:
                idx = parts.index('Models')
                if len(parts) > idx + 1:
                    model_dirs.add(parts[idx + 1])
        
        print("Models list:", sorted([m for m in model_dirs if m]))

        # Check for company / org models
        org_keywords = ['comp', 'dir', 'divisi', 'dept', 'cabang', 'sbu', 'sub', 'unit', 'holding', 'zona', 'kary']
        matching_models = [m for m in model_dirs if any(k in m.lower() for k in org_keywords)]
        print("Org-related models:", sorted(matching_models))
