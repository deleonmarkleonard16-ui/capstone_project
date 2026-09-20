// Run: node tests/browser/guidance-workflow.cjs
// Browser driver: npm install --prefix storage/app/testing-browser --no-save --package-lock=false playwright
const { chromium } = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = name => fs.readFileSync(path.join(__dirname, '../../public/js', name), 'utf8');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'chrome', args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.clock.install();
        await page.route('http://guidance.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));
        await page.goto('http://guidance.test/portal');
        await page.setContent(`<form id="guidance-track" action="http://guidance.test/api/track-request"><input name="reference" value="TR-BROWSER"><button>Track</button></form><div id="tracking-result"></div><div id="tracking-passes"></div>`);
        let status = 'Pending Payment', lookups = 0;
        await page.route('**/api/track-request', route => {
            lookups++;
            return route.fulfill({ json: { status, receipt_uploaded: status !== 'Pending Payment', details_html: status === 'Pending Payment' ? '<form data-guidance-receipt><input type="file" name="proof"><button>Upload</button></form>' : '<p>Receipt uploaded</p>',
                passes: [{ test: 'DASS-21 / PHQ-9 / GAD-7', status, qr_image: 'data:image/svg+xml;base64,PHN2Zy8+', direct_test_link: status === 'Approved' ? 'http://guidance.test/test/take/token' : null }] } });
        });
        await page.addScriptTag({ content: source('guidance-tracking.js') });
        await page.locator('#guidance-track button').click();
        await page.locator('input[type=file]').waitFor();
        await page.locator('input[type=file]').setInputFiles({ name: 'receipt.png', mimeType: 'image/png', buffer: Buffer.from('receipt') });
        await page.clock.fastForward(10000);
        await page.waitForFunction(() => document.querySelector('#tracking-result').textContent.includes('Pending Payment'));
        assert.equal(await page.locator('input[type=file]').evaluate(input => input.files.length), 1);
        await page.locator('input[type=file]').setInputFiles([]);
        status = 'Approved';
        await page.clock.fastForward(10000);
        await page.getByText('Click here if QR scanner is unavailable').waitFor();
        assert.ok(lookups >= 3, 'tracking should continue polling beyond one refresh');
        console.log('PASS tracking polling preserves receipt selection and renders the approved pass');

        const queue = await browser.newPage();
        queue.on('pageerror', error => errors.push(error.message));
        await queue.clock.install();
        await queue.route('http://guidance.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));
        await queue.goto('http://guidance.test/queue');
        await queue.setContent('<section data-live-queue data-json="true"><form data-live-filters><input name="q"><button>Search</button></form><p data-live-status></p><div data-live-results>No requests yet</div></section><div id="receipt-preview"></div><img id="receipt-image"><p id="receipt-error"></p><a id="receipt-original"></a>');
        await queue.route('**/queue*', route => route.fulfill({ json: { html: '<details data-review="42"><summary>Review</summary><button data-receipt="http://guidance.test/receipt.png">Show receipt</button></details>' } }));
        await queue.evaluate(() => { window.bootstrap = { Modal: { getOrCreateInstance: () => ({ show: () => { window.modalShown = true; } }) } }; });
        const receiptView = fs.readFileSync(path.join(__dirname, '../../resources/views/guidance/receipt-modal.blade.php'), 'utf8');
        await queue.addScriptTag({ content: receiptView.match(/<script>([\s\S]*?)<\/script>/)[1] });
        await queue.addScriptTag({ content: source('guidance-queue.js') });
        await queue.clock.fastForward(5000);
        await queue.getByText('Review', { exact: true }).click();
        await queue.getByText('Show receipt', { exact: true }).click();
        assert.equal(await queue.evaluate(() => window.modalShown), true);
        assert.equal(await queue.locator('#receipt-image').getAttribute('src'), 'http://guidance.test/receipt.png');
        await queue.locator('[name=q]').fill('Maria');
        await queue.clock.fastForward(400);
        await queue.waitForURL('**/queue?q=Maria');
        assert.equal(await queue.locator('details').evaluate(item => item.open), true);
        console.log('PASS queue polling, live search, open review preservation and delegated receipt preview');

        const review = await browser.newPage();
        review.on('pageerror', error => errors.push(error.message));
        await review.clock.install();
        await review.route('http://guidance.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));
        await review.goto('http://guidance.test/staff');
        await review.setContent('<button data-guidance-review="http://guidance.test/review/1">Review Details</button><div id="guidance-review-modal"><div class="modal-body"><p id="guidance-review-message"></p><div id="guidance-review-body"></div></div><button id="guidance-review-refresh">Refresh details</button></div>');
        await review.evaluate(() => { window.bootstrap = { Modal: { getOrCreateInstance: () => ({ show() {} }) } }; });
        let reviewState = 'Receipt Uploaded', verifications = 0;
        await review.route('**/review/1', route => route.fulfill({ json: { html: reviewState === 'Receipt Uploaded'
            ? '<p>G-8A2F</p><form data-review-verify action="http://guidance.test/verify/1"><input name="_token" value="test"><button>Verify &amp; Generate QR</button></form>'
            : reviewState === 'Approved' ? '<p>G-8A2F Approved</p><a href="/test/take/secret">Click here if QR scanner is unavailable</a>'
                : '<p>Completed</p><pre>Raw item answers (JSON)</pre>' } }));
        await review.route('**/verify/1', route => {
            assert.equal(route.request().method(), 'POST');
            verifications++;
            reviewState = 'Approved';
            return route.fulfill({ json: { message: 'Receipt verified.' } });
        });
        await review.addScriptTag({ content: source('guidance-review.js') });
        await review.getByText('Review Details', { exact: true }).click();
        await review.getByText('Verify & Generate QR', { exact: true }).click();
        await review.getByText('Click here if QR scanner is unavailable').waitFor();
        assert.equal(verifications, 1);
        reviewState = 'Completed';
        await review.clock.fastForward(5000);
        await review.getByText('Raw item answers (JSON)').waitFor();
        assert.equal(await review.locator('#guidance-review-body a').count(), 0);
        console.log('PASS review modal verifies by AJAX, displays the pass and refreshes completed results');

        // Use another page so tracking timers cannot interfere with assessment requests.
        const exam = await browser.newPage();
        exam.on('pageerror', error => errors.push(error.message));
        await exam.clock.install();
        await exam.route('http://guidance.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));
        await exam.goto('http://guidance.test/test/take/token');
        const steps = [['dass21', 21], ['phq9', 9], ['gad7', 7]].map(([test, count], index) => `<div class="assessment-step" data-test="${test}">${Array.from({ length: count }, (_, item) => `<div class="item-card"><input type="radio" name="answers[${test}][${item + 1}]" value="2"></div>`).join('')}${index < 2 ? '<button class="next-step" type="button">Next</button>' : '<button type="submit">Submit</button>'}</div>`).join('');
        await exam.setContent(`<div id="assessment-lock"><h1 id="lock-title"></h1><p id="lock-message"></p><button id="start-assessment">Start</button></div><section id="assessment-content" inert><span id="current-step-label"></span><span id="timer-display"></span><form id="assessment-form" action="/submit" data-start="/start" data-progress="/progress" data-state="/state" data-remaining="600" data-started="false"><input name="_token" value="test"><fieldset id="answer-fields" disabled>${steps}</fieldset></form><p id="submission-message"></p></section><section id="assessment-complete" hidden>Completed</section>`);
        let snapshots = [], expired = false, sectionIndex = 0;
        await exam.route('**/start', route => route.fulfill({ json: { status: 'In-Progress', remaining_seconds: 600, section_index: sectionIndex } }));
        await exam.route('**/progress', route => {
            snapshots.push(route.request().postDataJSON().answers);
            return route.fulfill({ json: { status: 'In-Progress', remaining_seconds: expired ? 0 : 599, section_index: sectionIndex, answers: snapshots.at(-1) } });
        });
        await exam.route('**/state', route => route.fulfill({ json: { status: expired ? 'Completed' : 'In-Progress', remaining_seconds: expired ? 0 : 599 } }));
        await exam.route('**/submit', route => {
            const body = route.request().postDataJSON();
            assert.equal(body.section_index, sectionIndex);
            snapshots.push(body.answers); sectionIndex++;
            return route.fulfill({json:{status:'In-Progress', remaining_seconds:600, section_index:sectionIndex, answers:body.answers}});
        });
        await exam.addScriptTag({ content: source('guidance-assessment.js') });
        await exam.locator('#start-assessment').click();
        await exam.waitForFunction(() => document.getElementById('assessment-lock').hidden);
        await exam.locator('.next-step').first().click();
        assert.equal(await exam.locator('#current-step-label').textContent(), '1', 'cannot skip unanswered DASS-21');
        await exam.locator('input[name="answers[dass21][1]"]').check();
        // Losing focus disables the fieldset, but saving must still include its checked radio.
        await exam.evaluate(() => window.dispatchEvent(new Event('blur')));
        await exam.waitForFunction(() => document.getElementById('submission-message').textContent === 'Answers saved.');
        assert.equal(snapshots.at(-1).dass21['1'], 2);
        assert.equal(await exam.locator('#answer-fields').evaluate(fieldset => fieldset.disabled), true);
        await exam.locator('#start-assessment').click();
        await exam.waitForFunction(() => document.getElementById('assessment-lock').hidden);
        await exam.locator('[data-test=dass21] input').evaluateAll(inputs => inputs.forEach(input => { input.checked = true; }));
        await exam.locator('.next-step').first().click();
        await exam.waitForFunction(() => document.getElementById('current-step-label').textContent === '2');
        assert.equal(sectionIndex, 1, 'server advances completed section');
        await exam.waitForFunction(() => document.getElementById('timer-display').textContent === '10:00');
        expired = true;
        await exam.clock.fastForward(600000);
        await exam.locator('#assessment-complete').waitFor({ state: 'visible' });
        console.log('PASS assessment saves locked answers, enforces sequence and finalizes at the deadline');
        const receipt = await browser.newPage();
        receipt.on('pageerror', error => errors.push(error.message));
        await receipt.context().grantPermissions(['camera'], {origin:'http://localhost'});
        await receipt.route('http://localhost/**', route => route.fulfill({contentType:'text/html', body:'<html><body></body></html>'}));
        await receipt.goto('http://localhost/batch');
        await receipt.setContent('<button id="camera-start">Take photo</button><button id="file-choice">Upload file</button><video id="receipt-camera" autoplay muted playsinline hidden></video><button id="camera-capture" hidden>Capture</button><canvas id="receipt-canvas" hidden></canvas><input id="receipt-file" type="file"><img id="receipt-preview" hidden><p id="camera-message"></p>');
        await receipt.addScriptTag({content:source('guidance-batch.js')});
        await receipt.locator('#camera-start').click();
        await receipt.waitForFunction(() => document.getElementById('receipt-camera').videoWidth > 0);
        await receipt.locator('#camera-capture').click();
        await receipt.waitForFunction(() => document.getElementById('receipt-file').files.length === 1);
        assert.equal(await receipt.locator('#receipt-file').evaluate(input => input.files[0].type), 'image/jpeg');
        assert.equal(await receipt.locator('#receipt-camera').evaluate(video => video.srcObject), null);
        await receipt.locator('#receipt-file').setInputFiles({name:'gallery.png', mimeType:'image/png', buffer:Buffer.from('gallery')});
        assert.equal(await receipt.locator('#receipt-file').evaluate(input => input.files[0].name), 'gallery.png');
        console.log('PASS batch camera capture creates a JPEG, stops the camera, and allows gallery replacement');

        const waiting = await browser.newPage();
        waiting.on('pageerror', error => errors.push(error.message));
        await waiting.clock.install();
        await waiting.route('http://guidance.test/**', route => route.fulfill({contentType:'text/html', body:'<html><body></body></html>'}));
        await waiting.goto('http://guidance.test/batch');
        await waiting.setContent('<div id="batch-wait" data-state="/batch-state"></div>');
        let launched = false;
        await waiting.route('**/batch-state', route => route.fulfill({json:{status:launched ? 'In-Progress' : 'Pending Registration', attendance:'Ready', url:launched ? 'http://guidance.test/test/take/batch-pass' : null}}));
        await waiting.addScriptTag({content:source('guidance-batch.js')});
        await waiting.getByText('Ready in Session.', {exact:false}).waitFor();
        launched = true;
        await waiting.clock.fastForward(2000);
        await waiting.waitForURL('**/test/take/batch-pass');
        console.log('PASS batch waiting room polls and opens the synchronized assessment pass');
        assert.deepEqual(errors, [], 'no browser JavaScript errors');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
