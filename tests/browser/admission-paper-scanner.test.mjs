import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/admission-paper-scanner.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '');
function scanner(fetch) {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, {
            dataset: {lookup:'/lookup', csrf:'token', totalItems:'80'},
            classList: {add() {}, remove() {}}, textContent:'', disabled:true, hidden:true,
            addEventListener() {}, replaceChildren() {},
            removeAttribute(name) { delete this[name]; },
            getBoundingClientRect: () => ({left:0, top:0, width:800, height:450}),
            videoWidth:800, videoHeight:450,
        });
        return elements.get(id);
    };
    element('sheet').getContext = () => ({
        clearRect() {}, drawImage() {}, putImageData() {}, beginPath() {}, arc() {}, fill() {},
        getImageData: () => ({data:new Uint8ClampedArray(800*450*4), width:800, height:450}),
    });
    const window = {addEventListener() {}, jsQR: () => ({data:'CAT-A'})};
    vm.runInNewContext(source, {document:{getElementById:element}, window, fetch, detectAnswers:()=>[]});
    return {element, window, capture: () => element('capture').onclick()};
}
const response = name => ({ok:true, json:async()=>({name, session:'Session 1', batch:'Batch 2', application_number:name, url:`/encode/${name}`})});

test('captured paper QR displays verified name, session and batch and targets its record', async () => {
    let payload;
    const app = scanner(async (url, request) => { payload=JSON.parse(request.body); return response('Juan'); });
    await app.capture();
    assert.equal(payload.code, 'CAT-A');
    assert.equal(app.element('applicant-name').textContent, 'Juan');
    assert.equal(app.element('applicant-session').textContent, 'Session 1');
    assert.equal(app.element('applicant-batch').textContent, 'Batch 2');
    assert.equal(app.element('answers').action, '/encode/Juan');
});

test('a new paper without a readable QR clears the previous owner and disables saving', async () => {
    const app = scanner(async()=>response('Juan'));
    await app.capture();
    app.element('confirm-match').checked = true;
    app.window.jsQR = () => null;
    await app.capture();
    assert.equal(app.element('answers').action, undefined);
    assert.equal(app.element('applicant-name').textContent, '');
    assert.equal(app.element('record').disabled, true);
    assert.equal(app.element('confirm-match').checked, false);
    assert.match(app.element('message').textContent, /QR not detected/);
});

test('a late lookup response cannot replace the owner of a newer captured paper', async () => {
    let finish;
    let calls = 0;
    const app = scanner(() => ++calls === 1 ? new Promise(resolve=>{finish=resolve;}) : Promise.resolve(response('New owner')));
    const oldCapture = app.capture();
    await app.capture();
    finish(response('Old owner'));
    await oldCapture;
    assert.equal(app.element('applicant-name').textContent, 'New owner');
    assert.equal(app.element('answers').action, '/encode/New owner');
});

test('unknown and already submitted QR codes cannot retain a previous match', async () => {
    let status = 200;
    const app = scanner(async()=> status === 200 ? response('Juan') : {ok:false, status});
    await app.capture();
    for (status of [404,409]) {
        await app.capture();
        assert.equal(app.element('answers').action, undefined);
        assert.equal(app.element('record').disabled, true);
        assert.equal(app.element('applicant-name').textContent, '');
    }
});
