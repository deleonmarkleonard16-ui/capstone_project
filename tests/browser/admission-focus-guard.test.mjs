import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

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
    host.installAdmissionFocusGuard({page, host, active: () => enabled, report: type => { incidents.push(type); enabled = false; }});
    return {host, page, incidents, tick: () => tick(), enable: () => {enabled = true;}, loseFocus: () => {focused = false;}};
}

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
