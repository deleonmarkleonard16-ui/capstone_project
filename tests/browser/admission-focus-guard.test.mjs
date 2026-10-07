import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function keyEvent(type, properties) {
    const event = new Event(type, {cancelable: true});
    Object.assign(event, properties);
    return event;
}

test('PrintScreen key release alone is detected', () => {
    const guard = setup();
    guard.enable();
    const event = keyEvent('keyup', {key: 'PrintScreen'});
    guard.page.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(guard.incidents, ['print_screen']);
});

test('screenshot keydown, keyup and focus loss create one incident', () => {
    const guard = setup();
    guard.enable();
    guard.page.dispatchEvent(keyEvent('keydown', {key: 'PrintScreen'}));
    guard.page.dispatchEvent(keyEvent('keyup', {key: 'PrintScreen'}));
    guard.host.dispatchEvent(new Event('blur'));
    assert.deepEqual(guard.incidents, ['print_screen']);
});

test('browser-delivered Windows and shifted Mac screenshot shortcuts are detected', () => {
    for (const properties of [{key: 'S', metaKey: true, shiftKey: true}, {key: '$', metaKey: true, shiftKey: true}]) {
        const guard = setup();
        guard.enable();
        guard.page.dispatchEvent(keyEvent('keydown', properties));
        assert.deepEqual(guard.incidents, ['screenshot']);
    }
});

test('printing from browser menus is detected', () => {
    const guard = setup();
    guard.enable();
    guard.host.dispatchEvent(new Event('beforeprint'));
    assert.deepEqual(guard.incidents, ['print']);
});

test('ordinary typing and key repeats are not screenshot incidents', () => {
    const guard = setup();
    guard.enable();
    guard.page.dispatchEvent(keyEvent('keydown', {key: 's', shiftKey: true}));
    guard.page.dispatchEvent(keyEvent('keydown', {key: 'PrintScreen', repeat: true}));
    assert.deepEqual(guard.incidents, []);
});

function setup() {
    const host = new EventTarget();
    const page = new EventTarget();
    let tick;
    let enabled = false;
    let focused = true;
    page.hidden = false;
    page.fullscreenElement = {};
    page.hasFocus = () => focused;
    host.setInterval = callback => { tick = callback; return 1; };
    host.clearInterval = () => {};
    vm.runInNewContext(readFileSync(new URL('../../public/js/admission-focus-guard.js', import.meta.url), 'utf8'), {window: host});
    const incidents = [];
    const stop = host.installAdmissionFocusGuard({page, host, active: () => enabled, report: type => { incidents.push(type); enabled = false; }});
    return {host, page, incidents, stop, tick: () => tick(), enable: () => {enabled = true;}, loseFocus: () => {focused = false;}};
}

test('three touches report a restricted gesture once; normal scrolling and pinch zoom do not', () => {
    const guard = setup();
    guard.enable();
    for (const count of [1, 2]) guard.page.dispatchEvent(keyEvent('touchstart', {touches: Array(count).fill({})}));
    guard.page.dispatchEvent(new Event('touchcancel'));
    assert.deepEqual(guard.incidents, []);
    guard.page.dispatchEvent(keyEvent('touchstart', {touches: [{}, {}, {}]}));
    guard.page.dispatchEvent(keyEvent('touchstart', {touches: [{}, {}, {}, {}]}));
    assert.deepEqual(guard.incidents, ['restricted_gesture']);
});

test('touch cancellation checks actual focus instead of treating scrolling as a violation', () => {
    const guard = setup();
    guard.enable();
    guard.loseFocus();
    guard.page.dispatchEvent(new Event('touchcancel'));
    assert.deepEqual(guard.incidents, ['focus_loss']);
});

test('stopping the guard removes all security event listeners', () => {
    const guard = setup();
    guard.enable();
    guard.stop();
    guard.page.dispatchEvent(keyEvent('keydown', {key: 'PrintScreen'}));
    guard.host.dispatchEvent(new Event('blur'));
    guard.host.dispatchEvent(new Event('beforeprint'));
    assert.deepEqual(guard.incidents, []);
});

test('does not penalize the startup screen or normal answer sheet use', () => {
    const guard = setup();
    guard.host.dispatchEvent(new Event('blur'));
    guard.enable();
    guard.tick();
    assert.deepEqual(guard.incidents, []);
});

test('polling detects lost focus even without a blur event', () => {
    const guard = setup();
    guard.enable();
    guard.loseFocus();
    guard.tick();
    assert.deepEqual(guard.incidents, ['focus_loss']);
});

test('one departure stays one incident until warning is acknowledged', () => {
    const guard = setup();
    guard.enable();
    guard.host.dispatchEvent(new Event('blur'));
    guard.page.hidden = true;
    guard.page.dispatchEvent(new Event('visibilitychange'));
    guard.page.fullscreenElement = null;
    guard.page.dispatchEvent(new Event('fullscreenchange'));
    guard.tick();
    assert.deepEqual(guard.incidents, ['focus_loss']);
    guard.enable();
    guard.tick();
    assert.deepEqual(guard.incidents, ['focus_loss', 'tab_switch']);
});

test('fullscreen departure is detected without a fullscreenchange event', () => {
    const guard = setup();
    guard.enable();
    guard.page.fullscreenElement = null;
    guard.tick();
    assert.deepEqual(guard.incidents, ['fullscreen_exit']);
});
