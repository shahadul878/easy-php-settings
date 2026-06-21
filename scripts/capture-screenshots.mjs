import { chromium } from 'playwright';
import { mkdir } from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '..');
const assetsDir = path.join(pluginRoot, '.wordpress-org');

const baseUrl = 'http://plugin-dev.local';
const username = process.env.WP_ADMIN_USER || 'admin';
const password = process.env.WP_ADMIN_PASS || 'ScreenshotTemp1!';

const tabs = [
  { file: 'screenshot-1.png', tab: 'general_settings', label: 'General Settings' },
  { file: 'screenshot-2.png', tab: 'tools', label: 'Tools' },
  { file: 'screenshot-3.png', tab: 'php_settings', label: 'PHP Settings' },
  { file: 'screenshot-4.png', tab: 'extensions', label: 'Extensions' },
  { file: 'screenshot-5.png', tab: 'status', label: 'Status' },
  { file: 'screenshot-6.png', tab: 'about', label: 'About' },
];

async function login(page) {
  const context = page.context();
  await context.addCookies([
    {
      name: 'wordpress_test_cookie',
      value: 'WP Cookie check',
      domain: 'plugin-dev.local',
      path: '/',
    },
  ]);

  const response = await context.request.post(`${baseUrl}/wp-login.php`, {
    form: {
      log: username,
      pwd: password,
      'wp-submit': 'Log In',
      redirect_to: `${baseUrl}/wp-admin/`,
      testcookie: '1',
    },
    maxRedirects: 0,
  });

  if (response.status() >= 400) {
    throw new Error(`Login request failed with status ${response.status()}`);
  }

  await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
  if (page.url().includes('wp-login.php')) {
    const error = await page.locator('#login_error').textContent().catch(() => '');
    throw new Error(`Login failed for user "${username}": ${error || 'still on login page'}`);
  }
}

async function dismissNotices(page) {
  const dismissButtons = page.locator('.notice.is-dismissible .notice-dismiss');
  const count = await dismissButtons.count();
  for (let i = 0; i < count; i++) {
    await dismissButtons.nth(i).click().catch(() => {});
  }
}

async function captureTab(page, tabConfig) {
  const url = `${baseUrl}/wp-admin/tools.php?page=easy-php-settings&tab=${tabConfig.tab}`;
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForSelector('.easy-php-settings-app', { timeout: 30000 });
  await dismissNotices(page);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.addStyleTag({
    content: `
      #wpadminbar,
      #adminmenuback,
      #adminmenuwrap,
      #wpfooter,
      .notice,
      .update-nag { display: none !important; }
      #wpcontent, #wpbody, #wpbody-content { margin: 0 !important; padding: 0 !important; }
      .auto-fold #wpcontent { margin-left: 0 !important; }
    `,
  });
  await page.waitForTimeout(400);

  await page.screenshot({
    path: path.join(assetsDir, tabConfig.file),
    fullPage: false,
    animations: 'disabled',
  });

  console.log(`Saved ${tabConfig.file} (${tabConfig.label})`);
}

async function main() {
  await mkdir(assetsDir, { recursive: true });

  const chromiumPath = process.env.PLAYWRIGHT_CHROMIUM_PATH ||
    '/tmp/cursor-sandbox-cache/f538ee9219f07b722be802af3a88be1d/playwright/chromium-1193/chrome-linux/chrome';

  const browser = await chromium.launch({
    headless: true,
    executablePath: chromiumPath,
  });
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();

  try {
    await login(page);
    for (const tab of tabs) {
      await captureTab(page, tab);
    }
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
