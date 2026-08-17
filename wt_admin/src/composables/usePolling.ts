import { onUnmounted, ref } from 'vue'

/**
 * Re-run a fetch on a timer, with an honest "last updated" clock.
 *
 * Built for watching a session happen on a phone: the answer has to show up on the laptop within
 * seconds, and — just as important — the operator has to be able to tell a QUIET ladder from a
 * STOPPED page. Hence `secondsAgo`, which ticks every second whether or not anything arrived: a
 * screen that merely looks unchanged is indistinguishable from a screen that died, and this is the
 * one screen whose whole job is to be believed.
 *
 * A poll that is still in flight is never doubled. Without that, one slow response on a laptop that
 * has been left open all afternoon stacks requests until the tab is queueing more than it finishes.
 */
export function usePolling(task: () => Promise<void>, intervalMs = 4000) {
  const enabled = ref(true)
  const busy = ref(false)
  const secondsAgo = ref(0)
  let poll: ReturnType<typeof setInterval> | null = null
  let tick: ReturnType<typeof setInterval> | null = null

  async function run(): Promise<void> {
    if (busy.value) return
    busy.value = true
    try {
      await task()
      // Stamped only on success: a failed refresh must not make the age counter look fresh.
      secondsAgo.value = 0
    } catch {
      // The caller's fetcher owns its error state; polling only owns the clock.
    } finally {
      busy.value = false
    }
  }

  function start(): void {
    stop()
    poll = setInterval(() => {
      if (enabled.value) void run()
    }, intervalMs)
    tick = setInterval(() => {
      secondsAgo.value += 1
    }, 1000)
  }

  function stop(): void {
    if (poll !== null) clearInterval(poll)
    if (tick !== null) clearInterval(tick)
    poll = null
    tick = null
  }

  onUnmounted(stop)

  return { enabled, busy, secondsAgo, run, start, stop }
}
