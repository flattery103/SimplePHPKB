"""Exercise real category uploads and saved homepage layout preferences."""
import re
import pathlib
import urllib.parse
import urllib.request
import urllib.error


def check_category_layout(request, base, client, anonymous, token, image_bytes, replacement):
    def settings_version():
        return re.search(r'name="version" value="(\d+)"', request('branding')[1]).group(1)

    def upload(fields, image, filename='category.png', mime='image/png'):
        boundary = 'KBCategoryImageBoundary'
        fields = dict(csrf=token, action='save', **fields)
        payload = b''.join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode() for key, value in fields.items())
        payload += (f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode() + image + f'\r\n--{boundary}--\r\n'.encode()
        try:
            response = client.open(urllib.request.Request(base+'?route=categories', payload, {'Content-Type': 'multipart/form-data; boundary='+boundary}))
            return response.status, response.read().decode()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode()

    def rows(body):
        return re.findall(r'<article class="article-row\b.*?</article>', body, re.S)

    home = request('home')[1]
    assert 'compact-search' in home and 'How can we help?' not in home, 'minimal search preserved'
    assert 'category-default.svg' in rows(home)[0], 'unconfigured category has question-mark image'
    assert 'article-mark' not in home, 'large article numbers replaced'
    category_url = base+'?route=category-image&id=4'
    fields = {'id':'4', 'version':'1', 'name':'Network & connectivity'}
    assert upload(fields, image_bytes)[0] == 200, 'category image upload saved'
    response = anonymous.open(category_url)
    first_image = response.read()
    assert response.headers['Content-Type'] == 'image/png' and first_image.startswith(b'\x89PNG'), 'category image public'
    first_row = rows(request('home')[1])[0]
    assert 'route=category-image&amp;id=4' in first_row, 'published category image replaces article number'
    assert first_row.index('category-icon') < first_row.index('<figcaption>Network &amp; connectivity</figcaption>'), 'category name is underneath the image'
    fields['version'] = '2'
    assert upload(fields, b'<svg onload="alert(1)"></svg>')[0] == 422, 'forged category image rejected'
    assert upload(fields, image_bytes+b'x'*(2*1024*1024))[0] == 422, 'oversized category image rejected'
    large_image = (pathlib.Path(__file__).parent/'artifacts/too-many-pixels.png').read_bytes()
    assert upload(fields, large_image)[0] == 422, 'category image pixel limit enforced'
    assert upload(dict(fields, remove_image='1'), image_bytes)[0] == 422, 'ambiguous replacement/removal rejected'
    assert anonymous.open(category_url).read() == first_image, 'invalid uploads retain previous image'
    assert upload(fields, replacement)[0] == 200, 'category image replacement saved'
    assert anonymous.open(category_url).read() != first_image, 'replacement image served'
    assert request('categories', {'csrf':token,'action':'save',**fields,'remove_image':'1'})[0] == 409, 'stale category edit cannot remove replacement'
    fields['version'] = '3'
    assert request('categories', {'csrf':token,'action':'save',**fields,'remove_image':'1'})[0] == 200, 'category image removed'
    response = anonymous.open(category_url)
    assert response.headers['Content-Type'] == 'image/svg+xml' and b'?' in response.read(), 'removed image uses question-mark fallback'
    fields['version'] = '4'
    assert upload(fields, image_bytes)[0] == 200, 'image can be added again'
    assert request('categories', {'csrf':'bad','action':'save',**fields,'remove_image':'1'})[0] == 403, 'category edits require CSRF'
    assert request('categories', {'csrf':token,'action':'save','id':'4','version':'5','name':'Array test','remove_image[]':'1'})[0] == 400, 'category image controls reject arrays'
    assert upload({'name':'Uploaded on creation'}, image_bytes)[0] == 200, 'image upload supported when creating a category'
    assert request('category-image',id='999999')[0] == 404, 'unknown category image does not disclose files'
    assert request('category-image',id='../../config/config.php')[0] == 400, 'category image path input rejected'
    assert request('category-image',{'csrf':token},id='4')[0] == 405, 'category image endpoint is read only'
    assert anonymous.open(urllib.request.Request(category_url,method='HEAD')).status == 200, 'category image supports HEAD'
    try:
        anonymous.open(urllib.request.Request(base+'?route=categories',urllib.parse.urlencode({'csrf':token,'action':'save','name':'Unauthorized'}).encode()))
        raise AssertionError('anonymous category edit allowed')
    except urllib.error.HTTPError as e:
        assert e.code in (401,403), 'category editing requires login'

    version = settings_version()
    values = {'csrf':token,'version':version,'title':'Nuvera KB','subtitle':'','compact_home':'1'}
    assert request('branding', values)[0] == 200, 'compact homepage setting saved'
    compact = rows(anonymous.open(base+'?route=home').read().decode())
    assert compact and 'route=article&amp;id=2' in compact[0], 'compact mode retains featured-first ordering'
    assert all('featured-badge' not in row and 'article-meta' not in row for row in compact), 'compact rows omit only featured badges and update dates'
    assert all('<p>' in row and 'class="row-arrow"' in row for row in compact), 'compact rows retain excerpts and article links'
    assert 'category-icon' in compact[0] and '<figcaption>Network &amp; connectivity</figcaption>' in compact[0], 'compact rows retain category image and name'
    assert 'article-list compact-list' in request('home')[1], 'compact spacing selected for home'
    assert any('article-meta' in row and '<p>' in row for row in rows(request('browse')[1])), 'browse keeps regular detail'
    assert request('branding', dict(values,compact_home='0'))[0] == 409, 'stale settings cannot change layout'
    assert 'compact-list' in request('home')[1], 'conflicting save preserves compact preference'
    assert request('branding', dict(values,version=settings_version(),compact_home='invalid'))[0] == 400, 'invalid layout value rejected'
    assert request('branding', {'csrf':token,'version':settings_version(),'title':'Nuvera KB','subtitle':''})[0] == 200, 'unchecked compact mode returns to regular layout'
    standard = rows(request('home')[1])
    assert 'featured-badge' in standard[0] and 'article-meta' in standard[0] and '<p>' in standard[0], 'regular layout restores detail'
    assert len(standard) == len(compact), 'layout setting does not change article selection'
    assert [re.search(r'<p>(.*?)</p>', row, re.S).group(1) for row in compact] == [re.search(r'<p>(.*?)</p>', row, re.S).group(1) for row in standard], 'compact and regular modes show the same excerpts'
    print('PASS category images: upload/create/replace/remove, fallback, validation, stale-edit protection; compact homepage: persistence, visibility and ordering')
