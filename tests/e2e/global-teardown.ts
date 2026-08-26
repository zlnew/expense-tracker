import { exec } from 'node:child_process'

/**
 * The sqlite e2e stack runs as a named docker container (see
 * scripts/e2e-server.sh). Killing Playwright's docker CLIENT on teardown
 * does not stop the container — the daemon owns it — so without this hook
 * every suite run leaks a server and the next run dies on "port already
 * used". Removing the deterministic name here makes re-runs idempotent.
 *
 * No-op when the host-PHP branch served the suite (no container exists).
 */
export default async function globalTeardown() {
  const port = process.env.ET_E2E_PORT ?? '8015'
  await new Promise<void>((resolve) => {
    exec(
      `docker rm -f et-e2e-server-${port}`,
      (err) => {
        // Err is expected whenever the host-PHP branch was used or docker
        // is unavailable — teardown must never fail the suite over it.
        void err
        resolve()
      },
    )
  })
}
