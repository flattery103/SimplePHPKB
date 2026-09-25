"""Check optional site link/banner through real settings requests and shared layouts."""
import html
import re
import urllib.parse
import urllib.request
import urllib.error
from html.parser import HTMLParser


class Elements(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.tags = []
        self.feed(body)

    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, dict(attrs)))


def check_site_notices(request, base, anonymous, token):
    def version():
        return re.search(r'name="version" value="(\d+)"', request('branding')[1]).group(1)

    def links(body):
        return [attrs for tag, attrs in Elements(body).tags if tag == 'a' and 'header-custom-link' in attrs.get('class', '').split()]

    settings = request('branding')[1]
    assert 'name="header_link_text"' in settings and 'name="banner_text"' in settings, 'optional link and banner controls exist'
    home = anonymous.open(base+'?route=home').read().decode()
    assert not links(home) and 'class="site-banner"' not in home, 'new options default to hidden'
    assert 'A GOOD PLACE TO START' not in home, 'removed homepage heading remains absent'
    values = {'csrf':token, 'title':'Nuvera KB', 'subtitle':'', 'compact_home':'1', 'header_link_enabled':'1', 'header_link_text':'Tickets & <support>', 'header_link_url':'https://tickets.example.test/new?source=kb&team=it', 'header_link_new_window':'1', 'banner_enabled':'1', 'banner_text':'Outlook delays <script>alert(1)</script>\nIT is investigating.'}
    initial_version = version()
    assert request('branding', dict(values, version=initial_version))[0] == 200, 'link and banner enabled'
    for route in ['home', 'browse', 'article', 'login']:
        body = anonymous.open(base+'?'+urllib.parse.urlencode({'route':route, 'id':1})).read().decode()
        link = links(body)
        assert len(link) == 1 and link[0]['href'] == values['header_link_url'], 'ticket link on every reader page'
        assert link[0].get('target') == '_blank' and {'noopener','noreferrer'} <= set(link[0].get('rel','').split()), 'safe new-window attributes'
        assert html.escape(values['header_link_text']) in body and html.escape(values['banner_text']) in body, 'link text and multiline banner escaped'
        assert '<script>alert(1)</script>' not in body and 'class="site-banner"' in body, 'banner is plain text'
        assert body.index('</header>') < body.index('class="site-banner"') < body.index('class="shell"'), 'banner between header and page content'
    admin = request('admin')[1]
    assert admin.index('header-custom-link') < admin.index('Sign out'), 'ticket link next to sign out'
    assert 'class="site-banner"' in admin, 'admin pages include the banner'
    assert request('branding', dict(values, version=initial_version, banner_text='Stale banner'))[0] == 409, 'stale form cannot overwrite banner'
    assert 'Stale banner' not in request('home')[1], 'failed save leaves live banner unchanged'
    assert request('branding', dict(values, version=version(), csrf='bad'))[0] == 403, 'notice settings require CSRF'
    assert request('branding', dict(values, version=version(), header_link_url='javascript:alert(1)'))[0] == 422, 'unsafe link rejected over HTTP'
    malformed = dict(values, version=version()); malformed.pop('banner_text'); malformed['banner_text[]']='bad'
    assert request('branding', malformed)[0] == 400, 'array-shaped banner rejected'
    status, body, _ = request('branding', dict(values, version=version(), header_link_text='', banner_text='Unsaved banner text'))
    assert status == 422 and 'Unsaved banner text' in body, 'validation error preserves entered text'
    assert 'Unsaved banner text' not in request('home')[1], 'invalid settings are not published'
    values['header_link_new_window']='0'
    assert request('branding', dict(values, version=version()))[0] == 200, 'current-window choice saved'
    assert links(request('home')[1])[0].get('target', '_self') == '_self', 'current-window choice rendered'
    values['header_link_enabled']='0'
    assert request('branding', dict(values, version=version()))[0] == 200
    home=request('home')[1]
    assert not links(home) and 'class="site-banner"' in home, 'banner works independently with the link disabled'
    values['header_link_enabled']='1';values['banner_enabled']='0'
    assert request('branding', dict(values, version=version()))[0] == 200
    home=request('home')[1]
    assert links(home) and 'class="site-banner"' not in home, 'link works independently with the banner disabled'
    try:
        anonymous.open(urllib.request.Request(base+'?route=branding', urllib.parse.urlencode(dict(values,version=version())).encode()))
        raise AssertionError('anonymous notice update allowed')
    except urllib.error.HTTPError as error:
        assert error.code in (401,403), 'anonymous visitors cannot change notice settings'
    values.pop('header_link_enabled');values.pop('banner_enabled')
    assert request('branding', dict(values, version=version()))[0] == 200, 'unchecked controls disable optional features'
    home = anonymous.open(base+'?route=home').read().decode()
    assert not links(home) and 'class="site-banner"' not in home, 'disabled options disappear for readers'
    settings = request('branding')[1]
    assert html.escape(values['header_link_text']) in settings and html.escape(values['banner_text']) in settings, 'disabled values remain saved in settings'
    print('PASS optional link/banner: defaults, saved choices, public/admin display, escaping, safe URL/target, errors, authorization, conflict protection, disable/reuse')
