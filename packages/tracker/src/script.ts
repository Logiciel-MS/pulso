import { init, pageview, track } from './index';

/*
 * <script defer src="https://analytics.logicielms.com/pulso.js"
 *         data-site="gz_abc123"
 *         data-api="https://analytics.logicielms.com/collect"></script>
 *
 * For sites that are not React (WordPress, plain HTML...). Then: window.pulso.track('cotizar').
 */
const script = document.currentScript as HTMLScriptElement | null;
const data = script?.dataset ?? {};

init({
  siteId: data.site ?? '',
  endpoint: data.api ?? (script ? new URL('collect', script.src).href : ''),
  autoPageviews: data.autoPageviews !== 'false',
  autoEvents: data.autoEvents !== 'false',
  trackLocalhost: data.trackLocalhost === 'true',
  debug: data.debug === 'true',
});

(window as unknown as { pulso: unknown }).pulso = { track, pageview };
