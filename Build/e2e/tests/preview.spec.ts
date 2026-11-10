import { expect, test, type Page } from '@playwright/test';

async function logIn(page: Page, host = 'backend.test'): Promise<void> {
  await page.goto(`https://${host}/typo3/`);
  await page.locator('#t3-username').fill('admin');
  await page.locator('#t3-password').fill('Password1!');
  await page.locator('#t3-login-submit').click();
  await expect(page.locator('#typo3-contentIframe')).toBeAttached();
}

async function viewWebpage(page: Page, pageId: number): Promise<Page> {
  await page.goto(`https://backend.test/typo3/module/web/layout?id=${pageId}`);
  const popup = page.waitForEvent('popup');
  await page.frameLocator('#typo3-contentIframe').getByTitle('View webpage').click();

  return popup;
}

test('View webpage shows a hidden page on another domain', async ({ page }) => {
  await logIn(page);

  const preview = await viewWebpage(page, 4);

  await expect(preview).toHaveURL('https://site-b.test/hidden');
  await expect(preview.locator('h1')).toHaveText('Hidden page on site B');
});

test('View webpage shows a hidden page on another domain behind a reverse proxy', async ({ page }) => {
  await logIn(page);

  const preview = await viewWebpage(page, 5);

  await expect(preview).toHaveURL('https://site-c.test/hidden');
  await expect(preview.locator('h1')).toHaveText('Hidden page on site C');
});

test('View webpage shows a hidden page on another domain with a non-standard port', async ({ page }) => {
  await logIn(page);

  const preview = await viewWebpage(page, 7);

  await expect(preview).toHaveURL('https://site-d.test:8443/hidden');
  await expect(preview.locator('h1')).toHaveText('Hidden page on site D');
});

test('View of a content element scrolls to it on a hidden page on another domain', async ({ page }) => {
  await logIn(page);
  await page.goto('https://backend.test/typo3/record/edit?edit[tt_content][123]=edit');
  const popup = page.waitForEvent('popup');
  await page.frameLocator('#typo3-contentIframe').getByRole('button', { name: 'View', exact: true }).click();
  const preview = await popup;

  await expect(preview).toHaveURL('https://site-b.test/hidden#c123');
  await expect(preview.locator('#c123')).toBeInViewport();
});

test('An existing backend login on the other domain survives a preview', async ({ page }) => {
  await logIn(page, 'site-b.test');
  await logIn(page);

  const preview = await viewWebpage(page, 4);
  await expect(preview.locator('h1')).toHaveText('Hidden page on site B');

  await preview.goto('https://site-b.test/typo3/');
  await expect(preview.locator('#typo3-contentIframe')).toBeAttached();
});
