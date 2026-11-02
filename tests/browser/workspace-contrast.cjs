const {chromium} = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

function luminance(rgb) {
    return rgb.reduce((sum, value, index) => {
        const channel = value / 255;
        return sum + (channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4) * [.2126, .7152, .0722][index];
    }, 0);
}

(async () => {
    const bootstrap = process.env.BOOTSTRAP_CSS_PATH
        ? fs.readFileSync(process.env.BOOTSTRAP_CSS_PATH, 'utf8')
        : await fetch('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css').then(response => {
            assert.ok(response.ok, 'Bootstrap stylesheet must load');
            return response.text();
        });
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    try {
        const page = await browser.newPage({viewport: {width: 1100, height: 800}});
        const colors = ['success', 'danger', 'primary', 'secondary', 'warning', 'info'];
        const badges = colors.map(color => `<tr><td><span class="badge bg-${color}">${color}</span></td><td><span class="badge bg-${color} text-white">${color} with text-white</span></td><td><span class="badge bg-${color}-subtle text-${color}">${color} subtle</span></td></tr>`).join('');
        await page.setContent(`<body class="staff-interface"><main><h1>Table label contrast</h1><table class="table table-striped table-hover"><tbody><tr><td style="background-color:#d1fae5!important"><span data-course-badge="1-1" class="badge bg-success">BSIT</span></td><td style="background-color:#fee2e2!important"><span data-course-badge="1-2" class="badge bg-danger-subtle text-danger">BSED-SS</span></td></tr>${badges}</tbody></table><p>${colors.map(color => `<span class="badge bg-${color}-subtle text-${color}">${color} outside table</span>`).join(' ')}</p><div id="solid" class="bg-success text-white">Solid panel</div></main></body>`);
        await page.addStyleTag({content: bootstrap});
        await page.addStyleTag({path: path.resolve('public/css/workspace-ui.css')});

        async function checkContrast() {
            const values = await page.locator('.badge').evaluateAll(elements => elements.map(element => {
                const style = getComputedStyle(element);
                return {label: element.textContent, color: style.color.match(/[\d.]+/g).slice(0, 3).map(Number), background: style.backgroundColor.match(/[\d.]+/g).slice(0, 3).map(Number)};
            }));
            for (const value of values) {
                const levels = [luminance(value.color), luminance(value.background)].sort((a, b) => b - a);
                const ratio = (levels[0] + .05) / (levels[1] + .05);
                assert.ok(ratio >= 4.5, `${value.label}: contrast ${ratio.toFixed(2)} must be at least 4.5`);
            }
        }
        await checkContrast();
        await page.locator('[data-course-badge="1-1"]').evaluate(element => { element.className = 'badge bg-success text-white'; });
        await page.locator('tbody tr').first().hover();
        await checkContrast();
        assert.ok(await page.locator('[data-course-badge="1-1"]').evaluate(element => parseFloat(getComputedStyle(element).fontSize) >= 12));
        assert.equal(await page.locator('#solid').evaluate(element => getComputedStyle(element).color), 'rgb(255, 255, 255)', 'solid panels retain white text');
        await page.setViewportSize({width: 390, height: 844});
        await checkContrast();
        await page.locator('body').evaluate(element => element.classList.remove('staff-interface'));
        assert.equal(await page.locator('[data-course-badge="1-1"]').evaluate(element => getComputedStyle(element).color), 'rgb(255, 255, 255)', 'guest layouts retain Bootstrap styling');
        console.log('PASS badge contrast: course choices, six status colors, subtle badges, dynamic updates, hover, mobile, and guest isolation.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
