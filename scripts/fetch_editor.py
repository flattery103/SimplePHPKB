#!/usr/bin/env python3
"""Fetch the pinned, self-hosted TinyMCE Community distribution."""
import base64,hashlib,io,pathlib,tarfile,urllib.request
ROOT=pathlib.Path(__file__).resolve().parent.parent
VERSION='8.9.2'
URL=f'https://registry.npmjs.org/tinymce/-/tinymce-{VERSION}.tgz'
INTEGRITY='Qh6ADSC0x1F/KsrupfResNJFAaINJQBtfVjx5zxxWJ0ZrGtaKldtAXDMI6uEA57s2n+0rEtXmtbitiU4zV7+0Q=='
with urllib.request.urlopen(URL,timeout=90) as response:data=response.read()
if base64.b64encode(hashlib.sha512(data).digest()).decode()!=INTEGRITY:raise SystemExit('Editor integrity check failed')
out=ROOT/'public/assets/tinymce';out.mkdir(parents=True,exist_ok=True)
with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as archive:
    for member in archive.getmembers():
        relative=pathlib.PurePosixPath(member.name).relative_to('package')
        if str(relative)=='.':continue
        if '..' in relative.parts or member.issym() or member.islnk():raise SystemExit('Unsafe editor archive')
        destination=out.joinpath(*relative.parts)
        if member.isdir():destination.mkdir(parents=True,exist_ok=True);continue
        if not member.isfile():continue
        destination.parent.mkdir(parents=True,exist_ok=True)
        with archive.extractfile(member) as source, destination.open('wb') as target:
            while chunk:=source.read(65536):target.write(chunk)
print(f'TinyMCE {VERSION} installed in {out}')
