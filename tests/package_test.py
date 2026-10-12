import pathlib,zipfile,re
root=pathlib.Path(__file__).resolve().parent.parent
version=re.search(r"KB_VERSION = '([^']+)'",(root/'src/init.php').read_text()).group(1)
archive=root/f'dist/SimplePHPKB-v{version}.zip'
assert archive.exists(),'release archive exists'
with zipfile.ZipFile(archive) as z:
    names=set(z.namelist())
    for item in ['public/index.php','public/assets/tinymce/tinymce.min.js','vendor/autoload.php','vendor/ezyang/htmlpurifier/LICENSE','LICENSE','config/config.example.php','docs/INSTALL.md','docs/BACKUP.md','bin/kb.php','database/001_initial.sql']:
        assert 'SimplePHPKB/'+item in names,item
    assert 'SimplePHPKB/config/config.php' not in names
    assert all('/storage/' not in n or n.endswith('/storage/.gitkeep') for n in names),'no real stored data'
    assert all('/tests/' not in n and '/.git/' not in n for n in names),'no local test config or repository metadata'
    assert z.testzip() is None,'archive CRCs'
print('PASS package: required runtime/dependencies/docs/notices; no configuration, content, test artifacts, or repository metadata')
with zipfile.ZipFile(root/f'dist/SimplePHPKB-v{version}-update.zip') as z:
    names=set(z.namelist())
    for item in ['src/Usage.php','templates/usage.php','database/005_usage.sql','src/Branding.php','templates/branding.php','database/002_branding_featured.sql','database/003_category_images_layout.sql','database/004_header_link_banner.sql','src/Categories.php','public/assets/category-default.svg','src/Auth.php','public/assets/app.css']:
        assert 'SimplePHPKB/'+item in names,item
    assert all(not any('/'+part+'/' in n for part in ['config','storage','deploy','tests']) for n in names),'update contains no configuration, uploads, server settings, or tests'
    assert z.testzip() is None
print('PASS update package: feature files included; configuration, media and Apache settings excluded')

with zipfile.ZipFile(root/f'dist/SimplePHPKB-v{version}-update.zip') as z:
    import json,hashlib
    manifest=json.loads(z.read('SimplePHPKB/update-manifest.json'))
    assert manifest['version']==version
    for path,digest in manifest['files'].items():assert hashlib.sha256(z.read('SimplePHPKB/'+path)).hexdigest()==digest,path
    assert 'vendor/autoload.php' in manifest['files'] and 'public/assets/tinymce/tinymce.min.js' in manifest['files']
with zipfile.ZipFile(root/f'dist/SimplePHPKB-v{version}-dev.zip') as z:
    names=z.namelist()
    for path in ['scripts/package.py','tests/lifecycle_http.py','.github/workflows/ci.yml','vendor/autoload.php','.gitignore']:
        assert 'SimplePHPKB/'+path in names,path
    assert 'SimplePHPKB/config/config.php' not in names
print('PASS update manifest hashes, runtime dependencies and development bundle')
