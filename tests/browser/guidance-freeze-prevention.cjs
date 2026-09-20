const { chromium } = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'chrome' });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', err => errors.push(err.message));

        await page.route('http://guidance.test/**', route => route.fulfill({
            contentType: 'text/html',
            body: '<html><body></body></html>'
        }));

        await page.goto('http://guidance.test/staff');

        const queueHtml = `
            <section data-live-queue data-json="true">
                <form data-live-filters><input name="q"></form>
                <p data-live-status></p>
                <div data-live-results>
                    <table>
                        <tr>
                            <td><button id="show-receipt-btn" data-receipt="http://guidance.test/receipt.png">Show receipt</button></td>
                            <td><button id="review-btn" data-guidance-review="http://guidance.test/review/1">Review Details</button></td>
                        </tr>
                    </table>
                </div>
            </section>
            <button id="outside-action" onclick="this.dataset.clicked='true'">Outside Click</button>
        `;

        const receiptModal = fs.readFileSync(path.join(__dirname, '../../resources/views/guidance/receipt-modal.blade.php'), 'utf8');
        const reviewModal = fs.readFileSync(path.join(__dirname, '../../resources/views/guidance/review-modal.blade.php'), 'utf8');

        await page.setContent(queueHtml + receiptModal.split('@push')[0] + reviewModal.split('@push')[0]);
        await page.addStyleTag({ content: fs.readFileSync(path.join(__dirname, '../../public/vendor/bootstrap.min.css'), 'utf8') });

        // Mock Bootstrap Modal
        await page.evaluate(() => {
            window.bootstrap = {
                Modal: {
                    getOrCreateInstance: modal => ({
                        show() {
                            modal.dispatchEvent(new Event('show.bs.modal', { bubbles: true }));
                            modal.classList.add('show');
                            modal.style.display = 'block';
                            modal.setAttribute('aria-modal', 'true');
                            document.body.classList.add('modal-open');
                            document.body.style.overflow = 'hidden';
                            const backdrop = document.createElement('div');
                            backdrop.className = 'modal-backdrop show';
                            document.body.append(backdrop);
                            modal.dispatchEvent(new Event('shown.bs.modal', { bubbles: true }));
                        },
                        hide() {
                            modal.dispatchEvent(new Event('hide.bs.modal', { bubbles: true }));
                            modal.classList.remove('show');
                            modal.style.display = 'none';
                            modal.removeAttribute('aria-modal');
                            modal.dispatchEvent(new Event('hidden.bs.modal', { bubbles: true }));
                        }
                    })
                }
            };
        });

        let queueFetchCount = 0;
        await page.route('**/staff*', route => {
            queueFetchCount++;
            return route.fulfill({
                json: {
                    html: '<table><tr><td><button id="show-receipt-btn" data-receipt="http://guidance.test/receipt.png">Show receipt</button></td></tr></table>'
                }
            });
        });

        await page.route('**/receipt.png', route => route.fulfill({
            contentType: 'image/png',
            body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII=', 'base64')
        }));

        await page.route('**/review/1', route => route.fulfill({
            json: {
                html: '<p>Student Profile Data</p><template data-review-deferred><h4>Scores</h4></template>'
            }
        }));

        // Load scripts
        const receiptScript = receiptModal.match(/<script>([\s\S]*?)<\/script>/)[1];
        await page.addScriptTag({ content: receiptScript });
        await page.addScriptTag({ content: fs.readFileSync(path.join(__dirname, '../../public/js/guidance-queue.js'), 'utf8') });
        await page.addScriptTag({ content: fs.readFileSync(path.join(__dirname, '../../public/js/guidance-review.js'), 'utf8') });

        // Test 1: Click "Show receipt"
        await page.locator('#show-receipt-btn').click();
        await page.waitForFunction(() => document.getElementById('receipt-preview').classList.contains('show'));
        assert.equal(await page.locator('#receipt-image').getAttribute('src'), 'http://guidance.test/receipt.png');

        // Verify queue polling paused while modal was open
        const fetchesBefore = queueFetchCount;
        await page.evaluate(() => new Promise(resolve => setTimeout(resolve, 200)));
        assert.equal(queueFetchCount, fetchesBefore, 'queue polling must remain paused while modal is open');

        // Close receipt modal
        await page.evaluate(() => {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('receipt-preview')).hide();
        });

        // Verify no freeze, backdrop gone, outside clicks work
        assert.equal(await page.locator('.modal-backdrop').count(), 0);
        assert.equal(await page.evaluate(() => document.body.style.overflow), 'auto');
        await page.locator('#outside-action').click();
        assert.equal(await page.locator('#outside-action').getAttribute('data-clicked'), 'true');
        console.log('PASS Show receipt opens smoothly, pauses queue, cleans up backdrop on close, and allows clicks');

        // Reset outside click
        await page.evaluate(() => { delete document.getElementById('outside-action').dataset.clicked; });

        // Test 2: Click "Review Details"
        await page.locator('#review-btn').click();
        await page.getByText('Student Profile Data').waitFor();
        await page.getByText('Scores').waitFor();
        assert.equal(await page.locator('#guidance-review-title').textContent(), 'Review Details');

        // Close review modal
        await page.evaluate(() => {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('guidance-review-modal')).hide();
        });

        // Verify no freeze after review close
        assert.equal(await page.locator('.modal-backdrop').count(), 0);
        assert.equal(await page.evaluate(() => document.body.style.overflow), 'auto');
        await page.locator('#outside-action').click();
        assert.equal(await page.locator('#outside-action').getAttribute('data-clicked'), 'true');
        console.log('PASS Review Details opens, renders deferred content immediately, cleans up backdrop, and avoids UI lockup');

        assert.deepEqual(errors, []);
        console.log('ALL FREEZE PREVENTATIVE TESTS PASSED');
    } finally {
        await browser.close();
    }
})().catch(err => {
    console.error(err);
    process.exitCode = 1;
});
