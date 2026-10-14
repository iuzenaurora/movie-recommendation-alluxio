import ast,json,unittest,sys
from pathlib import Path
import yaml
root=Path(__file__).resolve().parents[1]
source_files=[root/'MovieLab.pyw']
for folder in ['src','serving','scripts','tests']:
    source_files.extend((root/folder).glob('*.py'))
for p in source_files: ast.parse(p.read_text(encoding='utf-8'),filename=str(p))
for p in root.glob('notebooks/*.ipynb'):
    nb=json.loads(p.read_text(encoding='utf-8')); assert nb['nbformat']==4
    for c in nb['cells']:
        if c['cell_type']=='code':
            source=''.join(c['source'])
            if not any(line.startswith(('!','%')) for line in source.splitlines()): ast.parse(source)
c=yaml.safe_load((root/'compose.yaml').read_text(encoding='utf-8')); assert {'namenode','datanode','alluxio-master','alluxio-worker','spark','mongo','api','web'}<=c['services'].keys()
suite=unittest.defaultTestLoader.discover(str(root/'tests'))
result=unittest.TextTestRunner(verbosity=2).run(suite)
if not result.wasSuccessful(): sys.exit(1)
print('Python, notebooks, Compose structure and recommendation tests passed.')
