// «Песочница» runs asynchronously (backend наряд GEN-3): the start answers 202 with a run id, the call is made by a queued
// job, and the screen polls the run until it is done. A prompt the size of a lesson takes a strong model 30–51 s — the
// synchronous sandbox cut such a call off at 60 s after the vendor had billed it.
import type { PlaygroundResult, PlaygroundRun, PlaygroundRunStatus } from './types'

export interface AwaitRunOptions {
  /** Between two reads of the run. */
  intervalMs?: number
  /** How long the screen waits before it gives up reading (the call itself goes on and stays in the journal). */
  timeoutMs?: number
  onStatus?: (status: PlaygroundRunStatus) => void
  sleep?: (ms: number) => Promise<void>
}

const defaultSleep = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms))

export async function awaitPlaygroundRun(
  start: () => Promise<{ id: string; status: PlaygroundRunStatus }>,
  read: (id: string) => Promise<PlaygroundRun>,
  { intervalMs = 1500, timeoutMs = 6 * 60 * 1000, onStatus, sleep = defaultSleep }: AwaitRunOptions = {},
): Promise<PlaygroundResult> {
  const started = await start()
  onStatus?.(started.status)
  let waited = 0
  for (;;) {
    const run = await read(started.id)
    onStatus?.(run.status)
    if (run.status === 'done' && run.result !== null) return run.result
    if (waited >= timeoutMs) {
      throw new Error(`Прогон ${started.id} не закончился за ${Math.round(timeoutMs / 1000)} с — вызов идёт дальше, его цена будет в журнале.`)
    }
    await sleep(intervalMs)
    waited += intervalMs
  }
}
