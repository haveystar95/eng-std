import { describe, expect, it } from 'vitest'
import { awaitPlaygroundRun } from '@/api/playgroundRun'
import type { PlaygroundResult, PlaygroundRun } from '@/api/types'

const RESULT: PlaygroundResult = {
  provider: 'openai',
  model: 'gpt-5.4',
  rawText: '{}',
  parsedJson: {},
  parseError: null,
  usage: { tokensIn: 7400, tokensOut: 4100, costUsd: '0.064448' },
  latencyMs: 41000,
  error: null,
}

// Backend наряд GEN-3: «песочница — тем же асинхронным job'ом с опросом, не синхронно в веб-запросе». Catches a screen that
// takes the start's 202 for the answer, stops polling before the run is done, or polls a run that never ends for ever.
describe('awaitPlaygroundRun', () => {
  it('starts the run, reads it until it is done and hands back its result, telling each state', async () => {
    const states: PlaygroundRun[] = [
      { id: 'r1', status: 'queued', result: null },
      { id: 'r1', status: 'running', result: null },
      { id: 'r1', status: 'done', result: RESULT },
    ]
    const seen: string[] = []
    const slept: number[] = []

    const result = await awaitPlaygroundRun(
      async () => ({ id: 'r1', status: 'queued' }),
      async (id) => {
        expect(id).toBe('r1')
        return states.shift() as PlaygroundRun
      },
      { intervalMs: 10, onStatus: (s) => seen.push(s), sleep: async (ms) => void slept.push(ms) },
    )

    expect(result).toEqual(RESULT)
    expect(seen).toEqual(['queued', 'queued', 'running', 'done'])
    expect(slept).toEqual([10, 10])
  })

  it('gives up reading a run that does not finish in time, and says the call goes on', async () => {
    await expect(
      awaitPlaygroundRun(
        async () => ({ id: 'r2', status: 'queued' }),
        async () => ({ id: 'r2', status: 'running', result: null }),
        { intervalMs: 100, timeoutMs: 300, sleep: async () => undefined },
      ),
    ).rejects.toThrow('не закончился')
  })
})
