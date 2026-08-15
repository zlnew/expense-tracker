import { defineConfig } from '@playwright/test'

const port = process.env.ET_E2E_PORT ?? '8015'
const baseURL = `http://127.0.0.1:${port}`

export default defineConfig({
  testDir: './tests/e2e',
  workers: 2,
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
