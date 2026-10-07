import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

// Exercise the actual exam script with a controlled DOM/network, including
// the period before the server acknowledges an incident.
function setup() {
    const elements = new Map();
    function element(id) {
        if (!elements.has(id)) {
            const target = new EventTarget();
            const classes = new Set();
            target.classList = {add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x)};
            target.querySelector = () => null;
            elements.set(id, target);
        }
        return elements.get(id);
    }
    const document = new EventTarget();
    Object.assign(document, {
        getElementById: element,
        querySelectorAll: () => [],
        documentElement: element('root'),
        hidden: false,
        hasFocus: () => true,
        fullscreenElement: {},
    });
    const requests = [];
    const context = vm.createContext({document, window: new EventTarget(), history: {pushState() {}},
        location: {href: '/exam', replace() {}}, installAdmissionFocusGuard() {},
        setTimeout() {}, setInterval() {}, clearInterval() {}, confirm: () => true,
        INITIAL_STRIKES: 0, STRIKE_URL: '/strike', CSRF: 'test',
        fetch: (url, options) => new Promise(resolve => requests.push({url, options, resolve})),
    });
    const blade = readFileSync(new URL('../../resources/views/admin/admission/take.blade.php', import.meta.url), 'utf8');
    const script = blade.slice(blade.indexOf('const kioskLock'), blade.lastIndexOf('</script>'));
    vm.runInContext(script, context);
    vm.runInContext('started = true;', context);
    return {element, document, requests, run: code => vm.runInContext(code, context)};
}

test('blackout hides and disables answers before reporting finishes, with one request per incident', async () => {
    const guard = setup();
    const request = guard.run("reportIncident('restricted_gesture')");
    assert.equal(guard.element('root').classList.contains('exam-protected'), true);
    assert.equal(guard.element('exam-shell').inert, true);
    assert.equal(guard.element('blackout').classList.contains('active'), true);
    assert.equal(guard.element('strike-modal').classList.contains('active'), true);
    assert.equal(guard.element('strike-continue').disabled, true);
    assert.match(guard.element('strike-badge').textContent, /Reporting incident/);
    guard.element('strike-continue').dispatchEvent(new Event('click'));
    assert.equal(guard.element('exam-shell').inert, true);
    await guard.run("reportIncident('focus_loss')");
    assert.equal(guard.requests.length, 1);
    assert.equal(JSON.parse(guard.requests[0].options.body).incident_type, 'restricted_gesture');
    guard.requests[0].resolve({ok: true, json: async () => ({strikes: 1, terminated: false})});
    await request;
    assert.equal(guard.element('strike-modal').classList.contains('active'), true);
    assert.equal(guard.element('strike-continue').disabled, false);
    assert.match(guard.element('strike-body').textContent, /Restricted Multi-touch/);
    guard.element('strike-continue').dispatchEvent(new Event('click'));
    assert.equal(guard.element('exam-shell').inert, false);
    assert.equal(guard.element('root').classList.contains('exam-protected'), false);
    // A new violation immediately after acknowledgement must not be dropped.
    const second = guard.run("reportIncident('print_screen')");
    assert.equal(guard.requests.length, 2);
    guard.requests[1].resolve({ok: true, json: async () => ({strikes: 2, terminated: false})});
    await second;
});

test('failed reporting keeps answers protected and shows a reporting error', async () => {
    const guard = setup();
    const request = guard.run("reportIncident('focus_loss')");
    guard.requests[0].resolve({ok: false});
    await request;
    assert.equal(guard.element('exam-shell').inert, true);
    assert.equal(guard.element('strike-modal').classList.contains('active'), true);
    assert.equal(guard.element('strike-continue').hidden, true);
    assert.match(guard.element('strike-title').textContent, /Interrupted/);
});

test('failed fullscreen restoration cannot reveal the answer sheet', async () => {
    const guard = setup();
    const request = guard.run("reportIncident('focus_loss')");
    guard.requests[0].resolve({ok: true, json: async () => ({strikes: 1, terminated: false})});
    await request;
    guard.document.fullscreenElement = null;
    guard.document.documentElement.requestFullscreen = async () => {throw new Error('Denied');};
    guard.element('strike-continue').dispatchEvent(new Event('click'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(guard.element('exam-shell').inert, true);
    assert.equal(guard.element('root').classList.contains('exam-protected'), true);
});

test('focus loss masks the warning while away and still requires acknowledgement on return', async () => {
    const guard = setup();
    guard.document.hasFocus = () => false;
    const request = guard.run("reportIncident('focus_loss')");
    guard.requests[0].resolve({ok: true, json: async () => ({strikes: 1, terminated: false})});
    await request;
    assert.equal(guard.element('root').classList.contains('exam-away'), true);
    guard.document.hasFocus = () => true;
    guard.run('updateAwayMask()');
    assert.equal(guard.element('root').classList.contains('exam-away'), false);
    assert.equal(guard.element('exam-shell').inert, true);
    assert.equal(guard.element('strike-modal').classList.contains('active'), true);
});

test('third screenshot strike remains protected and announces termination', async () => {
    const guard = setup();
    const request = guard.run("reportIncident('screenshot')");
    guard.requests[0].resolve({ok: true, json: async () => ({strikes: 3, terminated: true})});
    await request;
    assert.equal(guard.element('exam-shell').inert, true);
    assert.match(guard.element('strike-title').textContent, /Terminated/);
    assert.match(guard.element('strike-body').textContent, /Screenshot shortcut detected/);
    assert.equal(guard.run('sending'), true);
});
