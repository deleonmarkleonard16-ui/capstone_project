// Run: node tests/browser/confirmation-dialog.cjs
const {chromium} = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    try {
        const page = await browser.newPage({viewport: {width: 1000, height: 800}});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent('<form id="delete" data-confirm="Delete batch Batch 1? Assigned applicants will return to the masterlist."><input id="name" required><button id="delete-button" name="action" value="delete">Delete</button></form>');
        await page.addStyleTag({path: path.resolve('public/css/confirmation-dialog.css')});
        await page.addScriptTag({path: path.resolve('public/js/confirmation-dialog.js')});
        await page.evaluate(() => {
            window.submissions = [];
            document.addEventListener('submit', event => {
                event.preventDefault();
                window.submissions.push({action: event.submitter?.value, form: event.target.id});
            });
        });
        await page.click('#delete-button');
        assert.equal(await page.locator('dialog').count(), 0, 'HTML validation runs before confirmation');
        await page.fill('#name', 'Valid');
        await page.click('#delete-button');
        await page.waitForSelector('dialog[open]');
        assert.equal(await page.locator('.app-confirm-title').textContent(), 'Delete batch?');
        assert.equal(await page.locator('.app-confirm-accept').textContent(), 'Delete Batch');
        assert.equal(await page.evaluate(() => document.activeElement.className), 'app-confirm-button app-confirm-cancel');
        await page.keyboard.press('Escape');
        await page.waitForSelector('dialog:not([open])', {state: 'attached'});
        assert.deepEqual(await page.evaluate(() => window.submissions), []);
        assert.equal(await page.evaluate(() => document.activeElement.id), 'delete-button');
        await page.click('#delete-button');
        await page.click('.app-confirm-accept');
        await page.waitForFunction(() => window.submissions.length === 1);
        assert.deepEqual(await page.evaluate(() => window.submissions), [{action: 'delete', form: 'delete'}]);
        // Dynamically inserted forms and submitter-level confirmation retain the selected action.
        await page.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<form id="dynamic"><button id="archive" name="action" value="archive" data-confirm="Archive this cycle? It will become read-only.">Archive</button></form>'));
        await page.click('#archive');
        await page.click('.app-confirm-accept');
        await page.waitForFunction(() => window.submissions.length === 2);
        assert.deepEqual(await page.evaluate(() => window.submissions[1]), {action: 'archive', form: 'dynamic'});
        await page.evaluate(() => {
            window.result = null;
            AppConfirm('Delete batch <img src=x onerror=alert(1)>? Applicants return to the masterlist.').then(result => window.result = result);
        });
        assert.equal(await page.locator('dialog img').count(), 0, 'Dynamic messages are plain text');
        assert.match(await page.locator('.app-confirm-message').textContent(), /<img/);
        assert.equal(await page.evaluate(() => AppConfirm('Second operation?')), false, 'Concurrent confirmations cannot queue stale actions');
        await page.keyboard.press('Enter');
        await page.waitForFunction(() => window.result === false);
        await page.setViewportSize({width: 360, height: 640});
        await page.evaluate(() => { AppConfirm('Delete batch Batch 1? Assigned applicants will return to the masterlist.'); });
        const box = await page.locator('dialog').boundingBox();
        assert.ok(box.x >= 0 && box.x + box.width <= 360, 'Dialog fits a mobile viewport');
        await page.evaluate(() => AppConfirm.dismiss());
        assert.deepEqual(errors, []);
        console.log('PASS: validation, cancel/Escape, safe focus, single submit, submitter preservation, dynamic forms, safe text, concurrent calls, mobile layout.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
