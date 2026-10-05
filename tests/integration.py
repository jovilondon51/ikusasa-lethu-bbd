"""HTTP regressions against an isolated LOCAL *_test database.
Required env: DB_HOST=127.0.0.1, DB_PORT, DB_NAME=ikusasa_test, DB_USER,
DB_PASS, TEST_DATABASE=1. Import database/schema.sql into a NEW database first.
Optional PHP_BIN, PHP_INI and TEST_PORT. Never use production credentials.
This runner creates fixtures, starts its own PHP server, and removes test uploads.
"""
import io, json, os, re, shutil, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request, zipfile
from http.cookiejar import CookieJar
from pathlib import Path
from html.parser import HTMLParser
ROOT = Path(__file__).resolve().parents[1]
PHP = [os.environ.get('PHP_BIN', 'php')]
if os.environ.get('PHP_INI'): PHP += ['-c', os.environ['PHP_INI']]
BASE = 'http://127.0.0.1:' + os.environ.get('TEST_PORT', '18080')
checks = 0

def check(value, message):
    global checks
    if not value: raise AssertionError(message)
    checks += 1

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args): return None

class Client:
    def __init__(self):
        self.cookies = CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect())
    def request(self, path, fields=None, file=None, headers=None):
        headers = dict(headers or {})
        data = None
        if fields is not None:
            if file:
                boundary = 'ikusasa-' + os.urandom(8).hex()
                parts = []
                for key, value in fields.items(): parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
                name, content = file
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="project_file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + content + b'\r\n')
                parts.append(f'--{boundary}--\r\n'.encode())
                data = b''.join(parts); headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
            else: data = urllib.parse.urlencode(fields).encode()
        try: response = self.opener.open(urllib.request.Request(BASE + path, data=data, headers=headers), timeout=10)
        except urllib.error.HTTPError as error: response = error
        return response.status, response.read().decode(errors='replace'), response.headers
    def token(self, path):
        status, page, _ = self.request(path)
        check(status == 200, 'Form is accessible: ' + path)
        match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', page)
        check(bool(match), 'Form has a CSRF token: ' + path)
        return match[1]
    def login(self, username, role='learner'):
        path = '/admin/login.php' if role == 'admin' else '/login.php'
        token = self.token(path)
        old = next(iter(self.cookies)).value
        result = self.request(path, {'csrf_token': token, 'username': username, 'password': 'Test-only-pass123'})
        check(result[0] == 302, 'Login succeeds: ' + username)
        check(next(iter(self.cookies)).value != old, 'Session ID renews after login')

class FormParser(HTMLParser):
    def __init__(self): super().__init__(); self.current = None; self.forms = []
    def handle_starttag(self, tag, attributes):
        attributes = dict(attributes)
        if tag == 'form':
            check(self.current is None, 'Forms are not nested')
            self.current = {'post': attributes.get('method', '').lower() == 'post', 'csrf': False}
        if tag == 'input' and self.current is not None and attributes.get('name') == 'csrf_token':
            self.current['csrf'] = bool(re.fullmatch('[a-f0-9]{64}', attributes.get('value', '')))
    def handle_endtag(self, tag):
        if tag == 'form' and self.current is not None:
            self.forms.append(self.current); self.current = None

storage = tempfile.mkdtemp(prefix='ikusasa-http-')
env = dict(os.environ, UPLOAD_ROOT=storage, SESSION_SECURE='0')
log = tempfile.TemporaryFile()
server = None
try:
    subprocess.run(PHP + [str(ROOT / 'tests/fixture.php')], env=env, check=True, stdout=subprocess.PIPE)
    server = subprocess.Popen(PHP + ['-S', urllib.parse.urlparse(BASE).netloc, '-t', str(ROOT), str(ROOT / 'tests/router.php')], env=env, stdout=log, stderr=log)
    guest = Client()
    for _ in range(40):
        try:
            if guest.request('/health.php')[0] == 200: break
        except OSError: time.sleep(.1)
    check(guest.request('/health.php')[0] == 200, 'Database and storage health check succeeds')
    subprocess.run(['node', str(ROOT / 'tests/login-browser.js')], env=dict(env, TEST_BASE_URL=BASE), check=True)
    check(guest.request('/file.php?path=uploads/projects/anything.html')[0] == 401, 'Downloads require authentication')
    check(guest.request('/admin/dashboard.php')[0] == 302, 'Admin page requires login')
    check(guest.request('/login.php', {'username': 'test_learner', 'password': 'Test-only-pass123'})[0] == 403, 'Login rejects missing CSRF token')
    learner, other, admin, otheradmin = Client(), Client(), Client(), Client()
    learner.login('test_learner'); other.login('other_learner'); admin.login('test_admin', 'admin'); otheradmin.login('other_admin', 'admin')
    for client, paths in [(admin, ['/admin/dashboard.php', '/admin/learners.php', '/admin/staff.php', '/admin/content.php', '/admin/community.php', '/admin/password_requests.php', '/admin/attendance.php', '/admin/projects.php']), (learner, ['/learner/dashboard.php', '/learner/projects.php', '/learner/profile.php', '/learner/community.php', '/learner/content.php?view=1'])]:
        for path in paths:
            status, page, _ = client.request(path)
            check(status == 200, 'Page renders successfully: ' + path)
            parser = FormParser(); parser.feed(page)
            check(parser.current is None and all(not form['post'] or form['csrf'] for form in parser.forms), 'Every POST form contains a valid token: ' + path)
    check(learner.request('/admin/dashboard.php')[0] == 302, 'Learners cannot access admin pages')
    check('data-attendance-streak="0"' in learner.request('/learner/profile.php')[1], 'Missing past attendance and future marks do not create a streak')
    for client in [admin, otheradmin]:
        status, page, _ = client.request('/admin/learners.php?edit=1')
        check(status == 200 and 'value="Test Learner"' in page and 'value="test_learner"' in page, 'Every admin can open the learner edit form')
        parser = FormParser(); parser.feed(page)
        check(all(not form['post'] or form['csrf'] for form in parser.forms), 'Learner edit form has CSRF protection')
    edit = {'action': 'edit', 'learner_id': '1', 'full_name': 'Updated Learner', 'email': 'updated@example.invalid', 'username': 'updated_learner', 'grade': 'Grade 12', 'status': 'active'}
    check(otheradmin.request('/admin/learners.php', edit)[0] == 403, 'Learner editing rejects missing CSRF token')
    check(learner.request('/admin/learners.php', dict(edit, csrf_token=learner.token('/learner/profile.php')))[0] == 302, 'A learner cannot use the admin editing action')
    edit['csrf_token'] = otheradmin.token('/admin/learners.php?edit=1')
    check(otheradmin.request('/admin/learners.php', edit)[0] == 302, 'Admin who did not create the learner can save edits')
    updated = learner.request('/learner/profile.php')[1]
    check(all(value in updated for value in ['Updated Learner', 'updated@example.invalid', 'updated_learner', 'Grade 12']), 'Edited details are persisted and visible on the learner profile')
    updated_login = Client(); updated_login.login('updated_learner')
    check(updated_login.request('/learner/dashboard.php')[0] == 200, 'Changing username keeps the existing password valid')
    for key, value in [('email', 'other@example.invalid'), ('username', 'other_learner')]:
        status, page, _ = otheradmin.request('/admin/learners.php', dict(edit, **{key: value}))
        check(status == 200 and 'already used by another learner' in page and 'value="Updated Learner"' in page, 'Duplicate learner details show an error and preserve the edit form')
        check('updated@example.invalid' in learner.request('/learner/profile.php')[1], 'Duplicate edit leaves existing information unchanged')
    for key, value in [('email', 'invalid'), ('status', 'unknown'), ('full_name', ''), ('username', 'x' * 101), ('grade', '')]:
        check(otheradmin.request('/admin/learners.php', dict(edit, **{key: value}))[0] == 422, 'Invalid learner edit is rejected: ' + key)
    check(otheradmin.request('/admin/learners.php', dict(edit, learner_id='99999'))[0] == 404, 'Editing a missing learner is rejected')
    check(admin.request('/admin/learners.php?edit=invalid')[0] == 422, 'Invalid edit ID is rejected')
    check(otheradmin.request('/admin/learners.php', dict(edit, status='inactive'))[0] == 302, 'Admin can deactivate learner in edit form')
    check(updated_login.request('/learner/dashboard.php')[0] == 302, 'Deactivation through edit form revokes learner access')
    check(otheradmin.request('/admin/learners.php', edit)[0] == 302, 'Admin can reactivate learner in edit form')
    check(updated_login.request('/learner/dashboard.php')[0] == 302, 'Reactivation does not restore the revoked session')
    restore = dict(edit, full_name='Test Learner', email='learner@example.invalid', username='test_learner', grade='10', csrf_token=admin.token('/admin/learners.php?edit=1'))
    check(admin.request('/admin/learners.php', restore)[0] == 302, 'Another admin can update the same learner')
    learner.login('test_learner')
    check('0/1' in learner.request('/learner/dashboard.php')[1], 'Inactive completed lessons are excluded from progress')
    check('/community.php' in learner.request('/learner/dashboard.php')[1], 'Community appears in navigation')
    token = learner.token('/learner/projects.php')
    fields = {'csrf_token': token, 'action': 'create', 'title': 'Nested website', 'description': 'Test', 'language': 'HTML', 'code_content': ''}
    check(learner.request('/learner/projects.php', fields, ('evil.php', b'<?php echo 1;'))[0] == 422, 'Executable upload is rejected')
    bad = io.BytesIO()
    with zipfile.ZipFile(bad, 'w') as archive: archive.writestr('shell.php', '<?php echo 1;')
    check(learner.request('/learner/projects.php', fields, ('bad.zip', bad.getvalue()))[0] == 422, 'ZIP with executable file is rejected')
    good = io.BytesIO()
    with zipfile.ZipFile(good, 'w') as archive:
        archive.writestr('website/index.html', '<html><head><link rel="stylesheet" href="style.css"></head><body><h1>Hello</h1><script src="app.js"></script></body></html>')
        archive.writestr('website/style.css', 'h1 { color: red; }')
        archive.writestr('website/app.js', 'document.querySelector("h1").textContent = "Working";')
    check(learner.request('/learner/projects.php', fields, ('website.zip', good.getvalue()))[0] == 302, 'Valid nested website upload succeeds')
    page = learner.request('/learner/projects.php')[1]
    match = re.search(r'class="btn btn-sm btn-success edit-button" data-project-id="(\d+)" data-file-path="([^"]*index.html)"', page)
    check(bool(match), 'Nested project file has an editor button')
    project_id, file_path = match.groups()
    check('sandbox="allow-scripts"' in page and 'allow-same-origin' not in page, 'Preview iframe has an opaque origin')
    original = re.search(r'href="(/file.php\?path=[^"]+&amp;download=1)"', page).group(1).replace('&amp;', '&')
    check(learner.request(original)[0] == 200, 'Owner can download original project')
    check(other.request(original)[0] == 404, 'Another learner cannot download the project')
    preview_url = '/preview.php?' + urllib.parse.urlencode({'project_id': project_id, 'path': file_path})
    status, body, _ = learner.request(preview_url, headers={'Accept': 'application/json'})
    preview = json.loads(body)
    check(status == 200 and preview['ok'] and 'color: red' in preview['html'] and 'Working' in preview['html'], 'Nested preview bundles CSS and JavaScript')
    check(other.request(preview_url, headers={'Accept': 'application/json'})[0] == 404, 'Another learner cannot preview the project')
    file_url = '/file.php?' + urllib.parse.urlencode({'path': file_path})
    save = {'action': 'save_file', 'project_id': project_id, 'file_path': file_path, 'file_content': '<h1>Saved</h1>'}
    check(learner.request('/learner/projects.php', save, headers={'Accept': 'application/json'})[0] == 403, 'Save rejects missing CSRF token')
    save['csrf_token'] = token
    status, body, _ = learner.request('/learner/projects.php', save, headers={'Accept': 'application/json'})
    check(status == 200 and json.loads(body)['ok'], 'Owner receives a confirmed save result')
    status, body, _ = learner.request(file_url, headers={'Accept': 'application/json'})
    check(status == 200 and json.loads(body)['content'] == '<h1>Saved</h1>', 'Saved content is actually persisted')
    save['csrf_token'] = other.token('/learner/projects.php')
    check(other.request('/learner/projects.php', save, headers={'Accept': 'application/json'})[0] == 404, 'Another learner cannot edit the project')
    save['csrf_token'] = token; save['file_path'] = 'uploads/projects/missing.html'
    status, body, _ = learner.request('/learner/projects.php', save, headers={'Accept': 'application/json'})
    check(status == 422 and not json.loads(body)['ok'], 'A failed save has an error result')
    for index in range(3):
        fields['title'] = f'Snippet {index}'
        check(learner.request('/learner/projects.php', fields)[0] == 302, 'Snippet project is created')
    check(re.search(r'<h3>4</h3>\s*<p>Your Projects', learner.request('/learner/dashboard.php')[1]), 'Dashboard shows all four projects')
    check(learner.request('/learner/projects.php?delete=' + project_id)[0] == 200, 'Legacy GET delete does not change data')
    check(learner.request(file_url, headers={'Accept': 'application/json'})[0] == 200, 'GET delete leaves project files intact')
    check(learner.request('/learner/content.php?view=2')[0] == 404, 'Inactive lessons cannot be completed')
    content_token = learner.token('/learner/content.php?view=1')
    check(learner.request('/learner/content.php?view=1', {'csrf_token': content_token, 'mark_complete': ''})[0] == 302, 'Active content can be completed')
    check('1/1' in learner.request('/learner/dashboard.php')[1], 'Completed progress uses the same active total')
    check(admin.request('/admin/analytics.php')[0] == 200, 'Analytics queries run successfully')
    check(admin.request('/admin/attendance.php?date=invalid')[0] == 422, 'Invalid date input is rejected')
    check(admin.request('/admin/attendance_calendar.php?month=invalid')[0] == 422, 'Invalid month input is rejected')
    attendance_token = admin.token('/admin/attendance.php')
    from datetime import date, timedelta
    for days, attendance in [(21, 'present'), (14, 'absent'), (7, 'present')]:
        fields_attendance = {'csrf_token': attendance_token, 'mark_attendance': '', 'date': str(date.today() - timedelta(days=days)), 'attendance[1]': attendance, 'attendance[2]': 'present'}
        check(admin.request('/admin/attendance.php', fields_attendance)[0] == 200, 'Attendance is recorded')
    check('Three consecutive sessions' not in learner.request('/learner/profile.php')[1], 'A missed session breaks the badge streak')
    check('data-attendance-streak="1"' in learner.request('/learner/profile.php')[1], 'Profile displays one consecutive attended session')
    for days in [14, 0]:
        check(admin.request('/admin/attendance.php', {'csrf_token': attendance_token, 'mark_attendance': '', 'date': str(date.today() - timedelta(days=days)), 'attendance[1]': 'present', 'attendance[2]': 'present'})[0] == 200, 'Streak attendance update succeeds')
    check('Three consecutive sessions' in learner.request('/learner/profile.php')[1], 'Attendance save awards a consecutive-session badge')
    check('data-attendance-streak="4"' in learner.request('/learner/profile.php')[1], 'Profile displays four consecutive recorded sessions')
    today_fields = {'csrf_token': attendance_token, 'mark_attendance': '', 'date': str(date.today()), 'attendance[1]': 'late', 'attendance[2]': 'present'}
    check(admin.request('/admin/attendance.php', today_fields)[0] == 200, 'Late attendance is recorded')
    check('data-attendance-streak="0"' in learner.request('/learner/profile.php')[1], 'Late attendance resets the displayed streak')
    check(admin.request('/admin/attendance.php', dict(today_fields, **{'attendance[1]': 'present'}))[0] == 200, 'Present attendance restores the streak')
    account_token = admin.token('/admin/learners.php')
    check(admin.request('/admin/learners.php?toggle=1')[0] == 200, 'GET cannot toggle an account')
    check(learner.request('/learner/dashboard.php')[0] == 200, 'Account remains active after GET')
    check(admin.request('/admin/learners.php', {'csrf_token': account_token, 'toggle': '1'})[0] == 302, 'Admin can deactivate learner with POST')
    check(learner.request('/learner/dashboard.php')[0] == 302, 'Deactivated learner loses session access')
    check(admin.request('/admin/learners.php', {'csrf_token': account_token, 'toggle': '1'})[0] == 302, 'Admin can reactivate learner')
    check(learner.request('/learner/dashboard.php')[0] == 302, 'Reactivation does not restore an old session')
    learner.login('test_learner')
    stale_logout_token = learner.token('/learner/dashboard.php')
    learner.login('test_learner')
    status, page, headers = learner.request('/logout.php', {'csrf_token': stale_logout_token})
    check(status == 303 and headers['Location'] == '/logout.php?confirm=1', 'An old-tab logout form redirects to a fresh confirmation')
    check(learner.request('/learner/dashboard.php')[0] == 200, 'Stale logout token cannot end the current session')
    status, page, _ = learner.request('/logout.php?confirm=1')
    check(status == 200 and 'Confirm logout' in page and 'Test Learner' in page, 'Logout recovery shows the current learner account')
    check('href="/learner/dashboard.php"' in page, 'Learner can cancel logout and return to dashboard')
    recovered_token = learner.token('/logout.php?confirm=1')
    check(learner.request('/logout.php', {'csrf_token': recovered_token})[0] == 302, 'Fresh confirmation logs out successfully')
    check(learner.request('/learner/dashboard.php')[0] == 302, 'Recovery logout revokes authenticated access')
    check(learner.request('/logout.php?confirm=1')[2]['Location'] == '/index.php', 'Already signed-out recovery returns to welcome page')
    check(guest.request('/logout.php', {})[0] == 303, 'Expired-session logout starts recovery without a raw form error')
    check(guest.request('/logout.php?confirm=1')[2]['Location'] == '/index.php', 'Expired-session recovery returns to welcome page')
    learner.login('test_learner')
    staff_token = admin.token('/admin/staff.php')
    check(admin.request('/admin/staff.php', {'csrf_token': staff_token, 'reset': '2'})[0] == 200, 'Staff password reset succeeds')
    check(otheradmin.request('/admin/dashboard.php')[0] == 302, 'Password reset revokes another staff session')
    check(admin.request('/admin/learners.php', {'csrf_token': account_token, 'delete': '2'})[0] == 302, 'Learner deletion succeeds')
    check(other.request('/learner/dashboard.php')[0] == 302, 'Deleted learner loses access')
    check(admin.request('/logout.php', {}, headers={'Accept': 'application/json'})[0] == 403, 'JSON logout still rejects an invalid CSRF token')
    check(admin.request('/logout.php', {})[2]['Location'] == '/logout.php?confirm=1', 'Missing admin logout token requires confirmation')
    status, page, _ = admin.request('/logout.php?confirm=1')
    check(status == 200 and 'Test Admin' in page and 'href="/admin/dashboard.php"' in page, 'Admin recovery shows the right account and cancel destination')
    check(admin.request('/admin/dashboard.php')[0] == 200, 'Opening confirmation does not log out the admin')
    check(learner.request('/logout.php')[0] == 405, 'GET cannot log out a user')
    logout_token = learner.token('/learner/dashboard.php')
    check(learner.request('/logout.php', {'csrf_token': logout_token})[0] == 302, 'Protected logout succeeds')
    check(learner.request('/learner/dashboard.php')[0] == 302, 'Logged-out session cannot access learner pages')
    print(f'{checks} HTTP/database checks passed.')
finally:
    if server:
        server.terminate()
        try: server.wait(timeout=5)
        except subprocess.TimeoutExpired: server.kill(); server.wait()
    log.seek(0); output = log.read().decode(errors='replace')
    if 'Fatal error' in output or 'Uncaught' in output or 'Warning:' in output: print(output[-8000:])
    log.close(); shutil.rmtree(storage)
