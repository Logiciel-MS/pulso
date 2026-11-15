import { useEffect } from 'react';
import { init } from './index';
import type { PulsoOptions } from './types';

export { track, pageview } from './index';
export type { PulsoOptions, EventProps } from './types';

/**
 * Drop it once in your root layout:
 *
 * ```tsx
 * <Pulso siteId="gz_abc123" endpoint="https://analytics.logicielms.com/collect" />
 * ```
 *
 * Renders nothing. Works with Next.js (App and Pages Router), Vite and React Router.
 */
export function Pulso(props: PulsoOptions): null {
  useEffect(() => {
    init(props);
    // Not destroyed on unmount on purpose: layouts remount, the visit doesn't restart.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.siteId, props.endpoint]);
  return null;
}
