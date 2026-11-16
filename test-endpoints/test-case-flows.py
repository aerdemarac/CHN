"""Explicit local-only fictional persistence test; leaves created cases for review."""
import http.cookiejar, urllib.request, urllib.parse, urllib.error, re
base = 'http://127.0.0.1:8776/test-endpoints/'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
checks = 0

def check(value, label):
    global checks
    assert value, label
    checks += 1

def request(role='patient', data=None, query='', page='medical-cases.php'):
    url = base + page + '?role=' + role + query
    req = urllib.request.Request(url, data=None if data is None else urllib.parse.urlencode(data).encode())
    try:
        response = client.open(req)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.read().decode(), response.url

def form(role='patient', query='', triage=False):
    code, body, _ = request(role, query=query)
    check(code == 200, 'GET ' + role)
    forms = re.findall(r'<form\b.*?</form>', body, re.S)
    target = next(f for f in forms if ('value="update_triage"' if triage else 'data-case-submit') in f)
    return dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', target))

for role in ['patient','doctor','staff','admin','invalid','']:
    for page in ['medical-cases.php','feedback.php']:
        code, body, _ = request(role, page=page)
        check(code == 200, page + role)
        check('Warning:' not in body and 'Fatal error' not in body, 'clean render')
        check('../css/' in body, 'relocated assets')
        if role in ['patient','doctor','staff']:
            check('/test-endpoints/feedback.php?role=' + role in body, 'sidebar role')

valid = dict(department_id='1', doctor_id='601', case_title='Fictional test summary', case_description='Longer fictional complaint details.')
for field, value in [('case_title',''), ('case_title','x'*161), ('case_description',''), ('department_id','2'), ('doctor_id','602'), ('doctor_id','')]:
    data = form() | valid | {field:value}
    check(request(data=data)[0] == 422, 'reject invalid ' + field)
data = form() | valid | {'tier_color':'orange'}
check(request(data=data)[0] == 403, 'patient cannot set triage')
data = form() | valid | {'csrf_token':'forged'}
check(request(data=data)[0] == 403, 'CSRF')
created=[]
for role in ['patient','doctor']:
    data = form(role) | valid
    if role == 'doctor':
        for national in ['', '!', 'UNKNOWN', 'DEMO-902']:
            if national == 'DEMO-902': break
            check(request(role, data | {'national_id':national})[0] == 422, 'national validation')
        data['national_id']='DEMO-902'
        # Forged assignment must be replaced with the server doctor's own identity.
        data['doctor_id']='602'
        data['department_id']='2'
    code, body, url = request(role, data)
    check(code == 200 and 'case=' in url, 'persist ' + role)
    check('Fictional test summary' in body and 'Longer fictional complaint details.' in body, 'title and description')
    check((re.search(r'<dd>\s*Green\s*</dd>', body) or '<span>Green</span>' in body) and 'Active (open)' in body, 'green/open defaults')
    check('Diagnosis history' in body, 'diagnosis section preserved')
    if role == 'doctor':
        check('<dd>Demo Doctor A</dd>' in body and '<dd>Demo Internal Medicine</dd>' in body, 'server auto assignment')
        check('id="create-doctor_id"' not in body, 'doctor cannot choose assignment')
    case = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)['case'][0]
    created.append(case)
    check(request(role, data)[2] == url, 'idempotent replay')
    check(request(role, query='&case='+case)[0] == 200, 'refresh detail')
triage=form('doctor', '&case='+created[0], True) | {'tier_color':'purple'}
check(request('doctor', triage)[0] == 422, 'triage allowlist')
triage['tier_color']='yellow'
check('<span>Yellow</span>' in request('doctor', triage)[1], 'doctor triage update')
triage=form('staff', '&case='+created[0], True) | {'tier_color':'orange'}
check(request('staff', triage)[0] == 403, 'URL staff does not grant capability')
check('Diagnosis history' not in request('staff', query='&case='+created[0])[1], 'staff diagnosis scope')
def status_form(case):
    code, body, _ = request('patient', query='&case='+case)
    check(code == 200, 'status detail')
    target=next(f for f in re.findall(r'<form\b.*?</form>', body, re.S) if 'value="update_status"' in f)
    return dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', target))

for state in ['closed', 'open']:
    data=status_form(created[0]) | {'case_state':state}
    code, body, url=request('patient',data)
    check(code == 200 and ('<span>Closed</span>' if state == 'closed' else '<span>Active (open)</span>') in body, 'patient status '+state)
    check(re.search(r'<dd>\s*Yellow\s*</dd>', body), 'status does not change triage')
    check(request('patient',data)[2] == url, 'status replay')
data=status_form(created[0]) | {'case_state':'invalid'}
check(request('patient',data)[0] == 422, 'status allowlist')
data=status_form(created[0]) | {'case_state':'closed','case_id':created[1]}
check(request('patient',data)[0] == 422, 'cannot change another patient case')
check('<span>Active (open)</span>' in request('doctor',query='&case='+created[1])[1], 'other patient status unchanged')
data=status_form(created[0]) | {'case_state':'closed','csrf_token':'forged'}
check(request('patient',data)[0] == 403, 'status CSRF')
check(request('doctor',data)[0] == 403, 'doctor cannot call patient status action')
def doctor_status_form(case):
    code, body, _ = request('doctor', query='&case='+case)
    check(code == 200, 'doctor status detail')
    target=next(f for f in re.findall(r'<form\b.*?</form>', body, re.S) if 'value="update_doctor_status"' in f)
    return dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', target))
for state in ['closed','open']:
    data=doctor_status_form(created[1]) | {'case_state':state}
    code, body, url=request('doctor',data)
    check(code == 200 and ('<span>Closed</span>' if state == 'closed' else '<span>Active (open)</span>') in body, 'doctor status '+state)
    check(request('doctor',data)[2] == url, 'doctor status replay')
data=doctor_status_form(created[1]) | {'case_state':'invalid'}
check(request('doctor',data)[0] == 422, 'doctor status allowlist')
data=doctor_status_form(created[1]) | {'case_state':'closed','csrf_token':'forged'}
check(request('doctor',data)[0] == 403, 'doctor status CSRF')
data=doctor_status_form(created[1]) | {'case_state':'closed'}
check(request('staff',data)[0] == 403, 'staff cannot change doctor status')
check(request('patient',data)[0] == 403, 'patient cannot use doctor status action')
body=request('doctor',query='&case='+created[1])[1]
check('<summary aria-label="Edit medical triage">' in body and 'medical-cases__edit-pencil' in body, 'inline pencil editor')
outside=form('patient') | valid | {'department_id':'2','doctor_id':'602'}
code, body, url=request('patient',outside)
check(code == 200 and 'case=' in url, 'different department fixture')
outside_id=urllib.parse.parse_qs(urllib.parse.urlparse(url).query)['case'][0]
data=doctor_status_form(created[1]) | {'case_state':'closed','case_id':outside_id}
check(request('doctor',data)[0] == 422, 'doctor cannot change out-of-scope case')
check('<span>Active (open)</span>' in request('patient',query='&case='+outside_id)[1], 'out-of-scope status unchanged')
print(f'{checks} HTTP checks passed; persisted fictional case IDs: {created}')
