const {chromium} = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const form = `<form data-evaluation-form><textarea name="remarks" aria-label="Remarks"></textarea><label><input type="radio" name="recommendations" value="For Counseling">For Counseling</label><label><input type="checkbox" name="review_confirmed" value="1">Confirm review</label><ul><li data-evaluation-check="remarks"></li><li data-evaluation-check="recommendation"></li><li data-evaluation-check="confirmation"></li></ul><button name="action" value="save" formnovalidate>Save draft</button><button name="action" value="complete_and_archive">Archive</button></form>`;
        await page.setContent('<body><div id="review-body"></div></body>');
        await page.addScriptTag({path: path.resolve('public/js/guidance-evaluation.js')});
        await page.evaluate(markup => {
            document.getElementById('review-body').innerHTML = markup;
            window.submissions = [];
            document.querySelector('form').addEventListener('submit', event => {
                event.preventDefault();
                window.submissions.push(Object.fromEntries(new FormData(event.target, event.submitter)));
            });
        }, form);
        await page.waitForFunction(() => document.querySelector('[data-evaluation-check="remarks"]').textContent.includes('required'));
        await page.getByRole('button', {name: 'Archive', exact: true}).click();
        assert.equal(await page.evaluate(() => window.submissions.length), 0, 'incomplete archiving is blocked before AJAX handlers');
        await page.getByRole('button', {name: 'Save draft'}).click();
        assert.equal(await page.evaluate(() => window.submissions.length), 1, 'draft saving still works after an invalid archive attempt');
        await page.getByLabel('Remarks', {exact: true}).fill('Counselor reviewed the recorded results.');
        await page.getByLabel('For Counseling', {exact: true}).check();
        await page.getByRole('button', {name: 'Archive', exact: true}).click();
        assert.equal(await page.evaluate(() => window.submissions.length), 1, 'default notes and a recommendation do not imply confirmation');
        await page.getByLabel('Confirm review').check();
        assert.match(await page.locator('[data-evaluation-check="confirmation"]').textContent(), /confirmed/);
        await page.getByRole('button', {name: 'Archive', exact: true}).click();
        const saved = await page.evaluate(() => window.submissions.at(-1));
        assert.equal(saved.action, 'complete_and_archive');
        assert.equal(saved.review_confirmed, '1');
        assert.equal(saved.recommendations, 'For Counseling');
        assert.deepEqual(errors, []);
        console.log('PASS evaluation checklist: dynamic modal forms, incomplete archive prevention, draft saves, explicit confirmation, and submitter preservation.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
