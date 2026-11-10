import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  forbidOnly: !!process.env.CI,
  use: {
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
  },
});
