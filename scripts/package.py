#!/usr/bin/env python3
"""Build distribution and repository-ready source archives, excluding private state."""
import pathlib,zipfile,hashlib,re,json
ROOT=pathlib.Path(__file__).resolve().parent.parent
VERSION=re.search(r"KB_VERSION = '([^']+)'",(ROOT/'src/init.php').read_text()).group(1)
OUT=ROOT/'dist';OUT.mkdir(exist_ok=True)
base_files=['README.md','LICENSE','THIRD_PARTY_NOTICES.md','CHANGELOG.md','composer.json','composer.lock','config/config.example.php','storage/.gitkeep','.gitignore']
release_dirs=['src','public','templates','database','bin','deploy','docs','vendor']
source_dirs=['.github','src','public','templates','database','bin','deploy','docs','scripts','tests']
def paths(dirs,source=False):
    chosen=[ROOT/p for p in base_files]
    for directory in dirs:
        for file in (ROOT/directory).rglob('*'):
            relative=file.relative_to(ROOT)
            if not file.is_file() or file.is_symlink():continue
            if '__pycache__' in relative.parts or file.suffix=='.pyc':continue
            if str(relative).startswith('tests/artifacts/'):continue
            if source and str(relative).startswith('public/assets/tinymce/'):continue
            chosen.append(file)
    return sorted(set(chosen))
def build(name,files,manifest=False):
    target=OUT/name
    with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=8) as archive:
        for file in files:archive.write(file,'SimplePHPKB/'+file.relative_to(ROOT).as_posix())
        if manifest:
            data={'app':'SimplePHPKB','format':1,'version':VERSION,'minimum_version':'0.6.0','files':{p.relative_to(ROOT).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in files}}
            archive.writestr('SimplePHPKB/update-manifest.json',json.dumps(data,indent=2)+'\n')
    with zipfile.ZipFile(target) as archive:
        if archive.testzip():raise SystemExit('ZIP integrity failure')
    digest=hashlib.sha256(target.read_bytes()).hexdigest()
    print(f'{name}: {target.stat().st_size:,} bytes, SHA256 {digest}')
    return digest+'  '+name
for p in ['vendor/autoload.php','public/assets/tinymce/tinymce.min.js']:
    if not (ROOT/p).is_file():raise SystemExit('Missing runtime dependency: '+p)
lines=[build(f'SimplePHPKB-v{VERSION}.zip',paths(release_dirs)),build(f'SimplePHPKB-v{VERSION}-source.zip',paths(source_dirs,True))]
update_files=[p for p in paths(['src','public','templates','database','bin','docs','vendor'])
              if p.relative_to(ROOT).parts[0] not in ['config','storage'] and p.name!='.gitignore']
lines.append(build(f'SimplePHPKB-v{VERSION}-update.zip',update_files,manifest=True))
lines.append(build(f'SimplePHPKB-v{VERSION}-dev.zip',paths(source_dirs+['vendor'])))
(OUT/'SHA256SUMS.txt').write_text('\n'.join(lines)+'\n')
