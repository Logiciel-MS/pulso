import { act, createElement, StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Pulso, track } from '../src/react';
import { captureBeacons, ENDPOINT, reset } from './helpers';

beforeEach(reset);
afterEach(reset);

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe('<Pulso />', () => {
  it('starts tracking once, even in StrictMode', async () => {
    const { beacon, payloads } = captureBeacons();
    const root = createRoot(document.createElement('div'));
    await act(async () => {
      root.render(
        createElement(StrictMode, null, createElement(Pulso, { siteId: 'gz', endpoint: ENDPOINT })),
      );
    });
    expect(beacon).toHaveBeenCalledTimes(1);
    track('cotizar');
    expect((await payloads()).map((p) => p.e)).toEqual(['pageview', 'event']);
    await act(async () => root.unmount());
  });
});
