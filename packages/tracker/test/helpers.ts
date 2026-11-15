import { vi } from 'vitest';
import { destroy } from '../src';
import type { Payload } from '../src/types';

export const ENDPOINT = 'https://analytics.logicielms.com/collect';

/** Captures what the tracker sends via sendBeacon. */
export function captureBeacons() {
  const blobs: Blob[] = [];
  const beacon = vi.fn((_url: string, data?: BodyInit | null) => {
    blobs.push(data as Blob);
    return true;
  });
  Object.defineProperty(navigator, 'sendBeacon', { value: beacon, configurable: true, writable: true });
  return {
    beacon,
    async payloads(): Promise<Payload[]> {
      return Promise.all(blobs.map(async (b) => JSON.parse(await b.text()) as Payload));
    },
  };
}

export function setUrl(url: string) {
  (window as unknown as { happyDOM: { setURL(u: string): void } }).happyDOM.setURL(url);
}

export function reset() {
  destroy();
  vi.useRealTimers();
  setUrl('https://cliente.co/');
  document.body.innerHTML = '';
  Object.defineProperty(navigator, 'doNotTrack', { value: null, configurable: true });
  // happy-dom says it's an automated browser; real visitors aren't
  Object.defineProperty(navigator, 'webdriver', { value: false, configurable: true });
  Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
}
