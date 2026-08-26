import { defineConfig } from '@playwright/test'

const port = process.env.ET_E2E_PORT ?? '8015'
const baseURL = `http://127.0.0.1:${port}`

export default defineConfig({
  testDir: './tests/e2e',
  // Serial only: every spec mutates ONE shared seeded sqlite database
  // (balances, fund reserve, recurring rows), so parallel workers would
  // race each other's fixtures. Isolation comes from per-test navigation
  // and self-calibrating assertions instead.
  workers: 1,
  globalTeardown: './tests/e2e/global-teardown.ts',
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  outputDir: 'test-results/',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: 'bash tests/e2e/scripts/e2e-server.sh',
      url: `${baseURL}/login`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: 'npm run dev',
      url: 'http://localhost:5173/@vite/client',
      reuseExistingServer: true,
    },
  ],
})
