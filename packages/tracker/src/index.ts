import { Tracker } from './tracker';
import type { EventProps, PulsoOptions } from './types';

export type { PulsoOptions, AutoEventsOptions, EventProps } from './types';
export { Tracker } from './tracker';

/*
 * One tracker per page, shared through globalThis. Ojo: this matters because the
 * React entry, the main entry and the <script> build are separate bundles: without
 * this, `track()` from one of them wouldn't see the tracker created by another.
 */
const KEY = '__pulso__';
type Global = typeof globalThis & { [KEY]?: Tracker };

/** Starts tracking. Safe to call more than once: you always get the same tracker. */
export function init(options: PulsoOptions): Tracker {
  const g = globalThis as Global;
  if (!g[KEY]) g[KEY] = new Tracker(options);
  return g[KEY]!;
}

/** Custom event. No-op until `init()` runs (and during SSR). */
export function track(name: string, props?: EventProps): void {
  (globalThis as Global)[KEY]?.track(name, props);
}

/** Manual page view, for `autoPageviews: false`. */
export function pageview(url?: string): void {
  (globalThis as Global)[KEY]?.pageview(url);
}

/** Stops tracking and removes listeners. */
export function destroy(): void {
  const g = globalThis as Global;
  g[KEY]?.destroy();
  delete g[KEY];
}
