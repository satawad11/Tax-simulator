import { chromium, firefox } from 'playwright';

const baseUrl = (process.env.BASE_URL || 'http://localhost:8088').replace(/\/$/, '');
const browserName = process.env.PLAYWRIGHT_BROWSER || 'chromium';
const browserType = { chromium, firefox }[browserName];

if (!browserType) {
    throw new Error(`Unsupported PLAYWRIGHT_BROWSER: ${browserName}`);
}

const browser = await browserType.launch({
    headless: true,
    ...(process.env.PLAYWRIGHT_EXECUTABLE_PATH
        ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE_PATH }
        : {}),
});

const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const failures = [];

page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`${response.status()} ${response.url()}`);
});

const expectRoute = async (path, selector) => {
    const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded' });
    if (!response || response.status() >= 400) {
        throw new Error(`${path} returned ${response?.status() ?? 'no response'}`);
    }
    await page.locator(selector).first().waitFor({ state: 'visible' });
};

try {
    await expectRoute('/', 'h1');
    await expectRoute('/tax-simulator/pnd91', '[data-simulator][data-form-code="PND91"]');
    await page.locator('[data-metadata] > *').first().waitFor({ state: 'visible' });
    await expectRoute('/tax-simulator/pnd90', '[data-simulator][data-form-code="PND90"]');
    await page.locator('[data-metadata] > *').first().waitFor({ state: 'visible' });
    await expectRoute('/login', '[data-auth-page="login"]');
    await expectRoute('/knowledge', 'h1');
    await expectRoute('/faq', 'h1');

    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL(`${baseUrl}/login`);

    if (failures.length > 0) {
        throw new Error(failures.join('\n'));
    }

    console.log(`Browser smoke passed: ${browserName}; ${baseUrl}`);
} finally {
    await browser.close();
}
