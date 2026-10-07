// Run with the existing browser driver: node tests/browser/admission-mobile-flow.cjs
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require('../../storage/app/testing-browser/node_modules/playwright');

(async () => {
    const source = fs.readFileSync('resources/views/admin/admission/take.blade.php', 'utf8');
    const styles = source.match(/<style>[\s\S]*?<\/style>/)[0];
    const clean = text => text.replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/\{\{[\s\S]*?\}\}/g, 'Test Applicant');
    const kiosk = clean(source.slice(source.indexOf('<div id="kiosk-lock">'), source.indexOf('<div id="exam-shell">')));
    const header = clean(source.slice(source.indexOf('<div id="exam-header">'), source.indexOf('    @php')));
    const warning = source.slice(source.indexOf('<div id="blackout">'), source.indexOf('<script src='));
    const cards = Array.from({length:80}, (_, i) => `<div class="question-card" data-item="${i+1}"><div class="q-num">Item #${i+1}</div><div class="choices">${['A','B','C','D'].map(letter => `<label class="choice-label"><input type="radio" name="answers[${i+1}]" value="${letter}"><span>${letter}</span></label>`).join('')}</div></div>`).join('');
    const content = `<div id="exam-shell">${header}<form id="exam-form"><div class="exam-item-list">${cards}</div></form><div id="submit-bar"><button id="submit-btn" type="button">Submit Exam</button></div></div>`;
    const script = source.slice(source.indexOf('const kioskLock'), source.lastIndexOf('</script>'));
    const browser = await chromium.launch({headless:true, channel:'chrome'});
    try {
        for (const [width,height] of [[360,640],[320,568],[820,360],[1280,800]]) {
            const page = await browser.newPage({viewport:{width,height}, hasTouch:true, isMobile:width<=420});
            const errors = [];
            page.on('pageerror', e => errors.push(e.message));
            await page.route('http://admission.test/**', route => route.fulfill({contentType:'text/html',body:'<html></html>'}));
            await page.goto('http://admission.test/exam');
            await page.setContent(`<html><head><meta name="viewport" content="width=device-width,initial-scale=1">${styles}</head><body>${kiosk}${content}${warning}</body></html>`);
            await page.addScriptTag({content:fs.readFileSync('public/js/admission-focus-guard.js','utf8')});
            await page.addScriptTag({content:`const INITIAL_STRIKES=0, CSRF='test', STRIKE_URL='/strike', SUBMIT_URL='/submit', COMPLETE_URL='/complete', TERMINATED_URL='/terminated';
                window.reports=[]; window.fetch=async (url, options) => {
                    window.reports.push(JSON.parse(options.body));
                    return {ok:true,json:async()=>({strikes:window.reports.length,terminated:false})};
                };
                ${script}`});
            // Swipe the pre-fullscreen panel itself, including its side margin.
            // Setting scrollTop directly would miss broken touch scrolling.
            const cdp = await page.context().newCDPSession(page);
            const swipe = async (x, from, to) => {
                await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y:from}]});
                for (let y=from-20;y>to;y-=20) await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y}]});
                await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
            };
            const needsScroll = await page.evaluate(()=>document.scrollingElement.scrollHeight>innerHeight);
            if (needsScroll) {
                await swipe(8, height-180, 60);
                await page.waitForFunction(()=>document.scrollingElement.scrollTop>0);
                await page.locator('.instructions li').last().scrollIntoViewIfNeeded();
                assert.equal(await page.evaluate(()=>window.reports.length),0);
                const button = await page.locator('#enter-btn').boundingBox();
                assert.ok(button.y>=0 && button.y+button.height<=height, 'Fullscreen button stays in view');
            }
            await page.locator('#enter-btn').click();
            await page.waitForFunction(() => !!document.fullscreenElement);
            await page.locator('.choice-label').first().click();
            assert.equal(await page.locator('#ans-count').textContent(), '1');
            // Real browser touch input: ordinary upward swipe scrolls down the sheet.
            await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:width/2,y:height-100}]});
            for (let y=height-120;y>height/2;y-=30) await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:width/2,y}]});
            await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
            await page.waitForFunction(() => document.getElementById('exam-shell').scrollTop > 0);
            assert.equal(await page.evaluate(() => window.reports.length), 0);
            await page.locator('#submit-btn').scrollIntoViewIfNeeded();
            assert.equal(await page.locator('#submit-btn').isVisible(), true);
            assert.ok(await page.locator('#exam-shell').evaluate(el => el.scrollTop > 1000));
            // Screenshot shortcut hides the sheet; acknowledging preserves answers and scroll.
            const scroll = await page.locator('#exam-shell').evaluate(el=>el.scrollTop);
            await page.evaluate(() => document.dispatchEvent(new KeyboardEvent('keyup',{key:'PrintScreen'})));
            await page.waitForFunction(()=>document.getElementById('strike-modal').classList.contains('active'));
            assert.equal(await page.locator('#exam-shell').evaluate(el=>getComputedStyle(el).visibility), 'hidden');
            assert.equal(await page.evaluate(()=>window.reports[0].incident_type), 'print_screen');
            await page.locator('#strike-continue').click();
            assert.equal(await page.locator('#exam-shell').evaluate(el=>el.scrollTop), scroll);
            assert.equal(await page.locator('#ans-count').textContent(), '1');
            // Browser navigation triggers the existing warning/report path.
            await page.evaluate(()=>window.dispatchEvent(new PopStateEvent('popstate')));
            await page.waitForFunction(()=>window.reports.length===2);
            assert.equal(await page.evaluate(()=>window.reports[1].incident_type), 'back_button');
            await page.locator('#strike-continue').click();
            page.once('dialog', dialog => dialog.accept());
            await page.locator('#submit-btn').click();
            await page.waitForURL('http://admission.test/complete');
            assert.deepEqual(errors, []);
            console.log(`PASS ${width}x${height}: entry, touch scroll, last item, blackout, resume, navigation, submit`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
