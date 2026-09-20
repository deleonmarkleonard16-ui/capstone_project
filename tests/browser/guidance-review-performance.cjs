// Run: node tests/browser/guidance-review-performance.cjs
const { chromium } = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname, '../..', name), 'utf8');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII=', 'base64');

(async () => {
    const browser = await chromium.launch({headless:true, channel:'chrome'});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('http://guidance.test/**', route => route.fulfill({contentType:'text/html', body:'<html><body></body></html>'}));
        await page.goto('http://guidance.test/staff');
        const shell = read('resources/views/guidance/review-modal.blade.php').split('@push')[0];
        await page.setContent(`<button id="open" data-guidance-review="/review">Review Submission</button><button id="underlying" onclick="this.dataset.clicked='yes'">Underlying action</button>${shell}`);
        await page.addStyleTag({content:read('public/vendor/bootstrap.min.css')});
        // Simulate Bootstrap's show/hide events, including its asynchronous hide
        // boundary, so interrupted transitions and orphaned backdrops are repeatable.
        await page.evaluate(() => {
            window.bootstrap = {Modal:{getOrCreateInstance:modal => ({show() {
                modal.dispatchEvent(new Event('show.bs.modal', {bubbles:true}));
                modal.classList.add('show'); modal.style.display = 'block'; modal.setAttribute('aria-modal','true');
                document.body.classList.add('modal-open'); document.body.style.overflow = 'hidden';
                const backdrop = document.createElement('div'); backdrop.className = 'modal-backdrop show'; document.body.append(backdrop);
                modal.dispatchEvent(new Event('shown.bs.modal', {bubbles:true}));
            }})}};
            window.closeReview = () => {
                const modal = document.getElementById('guidance-review-modal');
                modal.dispatchEvent(new Event('hide.bs.modal', {bubbles:true}));
                modal.classList.remove('show'); modal.style.display = 'none'; modal.removeAttribute('aria-modal');
                modal.dispatchEvent(new Event('hidden.bs.modal', {bubbles:true}));
            };
        });
        const summary = '<template data-review-deferred><h3>Scores ready</h3><p>Depression: 12</p></template>';
        const raw = Array.from({length:60}, (_, i) => `<template data-review-deferred>Item ${i}: &lt;img src=x onerror=alert(1)&gt;\n</template>`).join('');
        let html = `<p>Student profile ready</p><div data-review-preview aria-busy="true"><div data-review-receipt-loading role="status">Loading receipt preview...</div><img data-review-receipt data-src="/receipt.png" loading="lazy" decoding="async" hidden><p data-review-receipt-error hidden>Preview unavailable</p></div>${summary}<pre id="raw">${raw}</pre>`;
        let releaseImage, imageRequests = 0;
        const imageGate = new Promise(resolve => { releaseImage = resolve; });
        await page.route('**/receipt.png', async route => { imageRequests++; await imageGate; await route.fulfill({contentType:'image/png', body:png}).catch(() => {}); });
        await page.route('**/review', route => route.fulfill({json:{html}}));
        await page.addScriptTag({content:read('public/js/guidance-review.js')});
        await page.locator('#open').click();
        await page.getByText('Student profile ready').waitFor();
        await page.getByText('Scores ready').waitFor();
        assert.equal(await page.locator('[data-review-receipt-loading]').isVisible(), true, 'slow image must not block scores');
        assert.equal(await page.locator('#guidance-review-title').textContent(), 'Review Submission');
        await page.waitForFunction(() => document.getElementById('raw').textContent.includes('Item 59:'));
        assert.equal(await page.locator('#raw img').count(), 0, 'raw choices remain text, never executable markup');
        releaseImage();
        await page.waitForFunction(() => document.querySelector('[data-review-receipt]').style.opacity === '1');
        assert.equal(await page.locator('[data-review-receipt-loading]').isVisible(), false);
        await page.locator('#guidance-review-refresh').click();
        await page.waitForFunction(() => document.getElementById('guidance-review-message').textContent.startsWith('Updated'));
        assert.equal(await page.locator('[data-review-receipt]').getAttribute('src'), '/receipt.png');
        assert.equal(imageRequests, 1, 'unchanged refresh must preserve the loaded receipt');
        console.log('PASS deferred scores/raw choices, safe text rendering, slow-image placeholder, and unchanged refresh');

        await page.evaluate(() => {
            const orphan = document.createElement('div'); orphan.className = 'modal-backdrop'; document.body.append(orphan);
            closeReview();
        });
        assert.equal(await page.locator('.modal-backdrop').count(), 0);
        assert.equal(await page.evaluate(() => document.body.style.overflow), 'auto');
        assert.equal(await page.locator('#guidance-review-body').textContent(), '');
        await page.locator('#underlying').click();
        assert.equal(await page.locator('#underlying').getAttribute('data-clicked'), 'yes');

        // Close while many fragments are still scheduled. They must never reappear.
        await page.locator('#open').click();
        await page.getByText('Student profile ready').waitFor();
        await page.evaluate(() => closeReview());
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        assert.equal(await page.locator('#guidance-review-body').textContent(), '');
        console.log('PASS close cancels rendering, removes orphan backdrops, and restores scrolling/clicks');

        html = '<p>New review</p><div data-review-preview><div data-review-receipt-loading>Loading image</div><img data-review-receipt data-src="/broken.png" loading="lazy" decoding="async" hidden><p data-review-receipt-error hidden>Preview unavailable</p></div>';
        await page.route('**/broken.png', route => route.fulfill({status:404,body:''}));
        await page.locator('#open').click();
        await page.getByText('Preview unavailable').waitFor();
        assert.equal(await page.locator('[data-review-receipt-loading]').isVisible(), false);
        assert.equal(await page.locator('#raw').count(), 0);
        await page.evaluate(() => {
            const other = document.createElement('div'); other.className = 'modal show'; other.id = 'other'; other.dataset.guidanceReviewModal = ''; other.setAttribute('aria-modal','true'); document.body.append(other);
            closeReview();
        });
        assert.ok(await page.locator('.modal-backdrop').count() > 0, 'another open modal keeps its backdrop');
        assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
        await page.evaluate(() => {
            const other = document.getElementById('other'); other.classList.remove('show'); other.removeAttribute('aria-modal');
            other.dispatchEvent(new Event('hidden.bs.modal', {bubbles:true})); other.remove();
        });
        assert.equal(await page.locator('.modal-backdrop').count(), 0);
        assert.equal(await page.evaluate(() => document.body.style.overflow), 'auto');
        console.log('PASS failed images show fallback, rapid reopen stays fresh, and other modals retain their backdrops');
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
