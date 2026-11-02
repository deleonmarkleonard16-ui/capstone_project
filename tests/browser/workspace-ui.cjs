const {chromium} = require('../../storage/app/testing-browser/node_modules/playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({headless:true, channel:'chrome'});
    try {
        const page = await browser.newPage({viewport:{width:1280,height:800}});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        let submitted;
        const markup = '<!doctype html><html><head><style>header{height:64px}.app-sidebar{position:fixed;top:64px;left:0;width:200px;height:200px}main{margin-left:210px}</style></head><body class="staff-interface"><header><button data-sidebar-toggle aria-expanded="true">Sidebar</button></header><aside class="app-sidebar"><a class="sidebar-link active" href="#">Queue</a></aside><main class="has-sidebar"><form method="post" action="/save"><div><label class="form-label">Name</label><input class="form-control" name="name" required></div><button name="action" value="save_and_archive">Save and archive</button></form><div id="results"><p>Existing results</p></div><div id="dynamic"></div></main></body></html>';
        await page.route('http://workspace.test/**', route => {
            if (route.request().method() === 'POST') submitted = route.request().postData();
            return route.fulfill({contentType:'text/html',body:markup});
        });
        async function enhance() {
            await page.addStyleTag({path:path.resolve('public/css/workspace-ui.css')});
            await page.addScriptTag({path:path.resolve('public/js/workspace-ui.js')});
        }
        await page.goto('http://workspace.test/queue');
        await enhance();
        await page.locator('[data-sidebar-toggle]').click();
        assert.equal(await page.locator('[data-sidebar-toggle]').getAttribute('aria-expanded'),'false');
        await page.reload(); await enhance();
        assert.equal(await page.locator('[data-sidebar-toggle]').getAttribute('aria-expanded'),'false');
        assert.equal(await page.locator('.sidebar-link').getAttribute('aria-current'),'page');
        const input = page.locator('input[name=name]');
        await input.focus();await input.blur();
        assert.equal(await input.getAttribute('aria-invalid'),'true');
        assert.equal(await page.locator('.ui-field-error').isVisible(),true);
        await input.fill('Maria');
        assert.equal(await input.getAttribute('aria-invalid'),null);
        assert.equal(await input.evaluate(el=>el.labels[0].textContent),'Name*');
        await page.evaluate(()=>document.querySelector('#dynamic').innerHTML='<form><label class="form-label">Program</label><select class="form-select" name="program" required><option value="">Choose</option><option>BSIT</option></select></form>');
        await page.waitForFunction(()=>document.querySelector('select').labels.length===1);
        await page.evaluate(()=>WorkspaceUI.setLoading(document.querySelector('#results'),true));
        assert.equal(await page.locator('#results').getAttribute('aria-busy'),'true');
        await page.evaluate(()=>WorkspaceUI.setLoading(document.querySelector('#results'),false));
        assert.equal(await page.locator('#results').textContent(),'Existing results');
        await page.evaluate(()=>WorkspaceUI.notify('<img src=x onerror=alert(1)>','danger'));
        assert.equal(await page.locator('.ui-notices img').count(),0);
        assert.equal(await page.locator('.ui-notices [role=alert]').count(),1);
        await page.locator('.ui-notices .btn-close').click();
        assert.equal(await page.locator('.ui-notices .alert').count(),0);
        await Promise.all([page.waitForNavigation(),page.locator('button[name=action]').click()]);
        const data = new URLSearchParams(submitted);
        assert.equal(data.get('name'),'Maria');
        assert.equal(data.get('action'),'save_and_archive','visual submit feedback must preserve named submitters');
        await page.setContent('<body><form><label class="form-label">Guest field</label><input class="form-control" required></form></body>');
        await page.addScriptTag({path:path.resolve('public/js/workspace-ui.js')});
        assert.equal(await page.locator('.ui-required').count(),0,'guest forms keep their own interface');
        assert.deepEqual(errors,[]);
        console.log('PASS workspace UI: persistent sidebar, active navigation, accessible validation, dynamic forms, skeleton state, safe dismissible notices, named submitter, guest isolation.');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
