const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const assert = require('assert');
(async () => {
    const args = process.env.PHP_INI ? ['-c', process.env.PHP_INI] : [];
    args.push(__dirname + '/preview-fixture.php');
    const html = execFileSync(process.env.PHP_BIN || 'php', args, { encoding: 'utf8' });
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.setContent('<h1>Safe parent</h1><iframe id="preview" sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe>');
        await page.locator('#preview').evaluate((frame, content) => frame.srcdoc = content, html);
        const frame = page.frameLocator('#preview');
        await frame.locator('body[data-network="blocked"]').waitFor();
        assert.equal(await frame.locator('h1').textContent(), 'Working');
        assert.equal(await frame.locator('h1').evaluate(node => getComputedStyle(node).color), 'rgb(255, 0, 0)');
        assert.equal(await page.locator('h1').textContent(), 'Safe parent');
        assert.equal(await frame.locator('body').getAttribute('data-parent'), 'blocked');
        assert.equal(await frame.locator('body').getAttribute('data-storage'), 'blocked');
        assert.equal(await frame.locator('body').getAttribute('data-network'), 'blocked');
        console.log('6 browser preview-isolation checks passed.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
