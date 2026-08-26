import { defineConfig } from '@playwright/test'

// THROWAWAY local-iteration config — expects `bash tests/e2e/scripts/e2e-server.sh`
// already running on :8015 (docker-backed). NOT part of the deliverable;
// deleted before commit. The committed playwright.config.ts boots its own stack.
const port = process.env.ET_E2E_PORT ?? '8015'
const baseURL = `http://127.0.0.1:${port}`

export default defineConfig({
  testDir: './tests/e2e',
  workers: 1,
  timeout: 30_000,
  expect: { timeout: 5_000 },
  retries: 0,
  reporter: [['list']],
  outputDir: 'test-results/',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
})
