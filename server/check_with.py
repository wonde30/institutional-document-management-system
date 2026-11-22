import glob

found = []
for f in glob.glob(r'c:\Users\IT3\Desktop\DMS\server\app\Models\*.php'):
    c = open(f, encoding='utf-8').read()
    if 'with' in c:
        for line in c.splitlines():
            if '$with' in line:
                found.append((f, line.strip()))

print(f"Total lines with $with: {len(found)}")
for f, l in found:
    print(f, l)
