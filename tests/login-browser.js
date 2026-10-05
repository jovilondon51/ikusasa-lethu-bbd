const { chromium } = require('playwright');
const assert = require('assert');
(async () => {
    const base = process.env.TEST_BASE_URL;
    if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Use only the isolated local test server.');
    const browser = await chromium.launch({ headless: true });
    let checks = 0;
    try {
        // A broken/unavailable main script and disabled storage must not break password controls.
        const context = await browser.newContext();
        await context.addInitScript(() => {
            Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage disabled'); } });
        });
        await context.route('**/*', route => {
            const url = new URL(route.request().url());
            if (url.origin !== new URL(base).origin || url.pathname === '/assets/js/main.js') return route.abort();
            return route.continue();
        });
        for (const path of ['/login.php', '/admin/login.php']) {
            const page = await context.newPage();
            let posts = 0;
            page.on('request', request => { if (request.method() === 'POST') posts++; });
            await page.goto(base + path);
            const password = page.locator('#password');
            const toggle = page.locator('[data-password-toggle="password"]');
            await password.fill('My-test-password123');
            assert.equal(await password.getAttribute('type'), 'password'); checks++;
            const script = await page.locator('script[src*="passwords.js"]').getAttribute('src');
            assert.match(script, /passwords\.js\?v=[a-f0-9]{12}$/); checks++;
            await toggle.click();
            assert.equal(await password.getAttribute('type'), 'text'); checks++;
            assert.equal(await toggle.textContent(), 'Hide password'); checks++;
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true'); checks++;
            assert.equal(await password.inputValue(), 'My-test-password123'); checks++;
            // Native button keyboard activation works too.
            await toggle.focus(); await page.keyboard.press('Space');
            assert.equal(await password.getAttribute('type'), 'password'); checks++;
            assert.equal(await toggle.textContent(), 'Show password'); checks++;
            assert.equal(posts, 0); checks++;
            await toggle.click();
            await page.locator('form').first().evaluate(form => {
                form.addEventListener('submit', event => event.preventDefault(), { once: true });
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            });
            assert.equal(await password.getAttribute('type'), 'password'); checks++;
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false'); checks++;
            assert.equal(await password.inputValue(), 'My-test-password123'); checks++;
            await page.close();
        }
        await context.close();
        console.log(`${checks} login password browser checks passed.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
