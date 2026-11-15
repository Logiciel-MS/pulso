import type { AutoEventsOptions, EventProps, Payload, PulsoOptions } from './types';

const MAX_ENGAGEMENT_MS = 30 * 60 * 1000;
const DOWNLOAD_EXT = /\.(pdf|zip|rar|7z|docx?|xlsx?|pptx?|csv|txt|mp3|mp4|apk|dmg|exe)$/i;
const WHATSAPP_HOSTS = /(^|\.)(wa\.me|whatsapp\.com)$/i;

const randomId = () => Math.random().toString(36).slice(2, 10);
const stripWww = (host: string) => host.replace(/^www\./i, '');

/**
 * Keeps event names and props small and boring on purpose: we never want a
 * form value, an email or a phone number to end up in the analytics by accident.
 */
export function sanitizeProps(props?: EventProps): EventProps | undefined {
  if (!props || typeof props !== 'object') return undefined;
  const clean: EventProps = {};
  let count = 0;
  for (const [key, value] of Object.entries(props)) {
    if (count >= 10) break;
    const k = String(key).slice(0, 32);
    if (typeof value === 'string') clean[k] = value.slice(0, 256);
    else if (typeof value === 'number' && Number.isFinite(value)) clean[k] = value;
    else if (typeof value === 'boolean') clean[k] = value;
    else continue;
    count++;
  }
  return count ? clean : undefined;
}

export class Tracker {
  readonly enabled: boolean;
  private readonly options: Required<Omit<PulsoOptions, 'autoEvents'>> & {
    autoEvents: Required<AutoEventsOptions>;
  };
  private currentUrl = '';
  private pageviewId = '';
  private visibleSince = 0;
  private engagedMs = 0;
  private firstPageview = true;
  private cleanup: Array<() => void> = [];

  constructor(options: PulsoOptions) {
    const auto = options.autoEvents;
    const allAuto = auto === undefined || auto === true;
    const pick = (key: keyof AutoEventsOptions) =>
      allAuto ? true : auto === false ? false : auto[key] !== false;

    this.options = {
      siteId: options.siteId,
      endpoint: options.endpoint,
      autoPageviews: options.autoPageviews !== false,
      respectDoNotTrack: options.respectDoNotTrack !== false,
      trackLocalhost: options.trackLocalhost === true,
      debug: options.debug === true,
      autoEvents: {
        whatsapp: pick('whatsapp'),
        phone: pick('phone'),
        email: pick('email'),
        outbound: pick('outbound'),
        downloads: pick('downloads'),
        forms: pick('forms'),
      },
    };
    this.enabled = this.shouldTrack();
    if (!this.enabled) {
      this.log('disabled');
      return;
    }

    this.listen(document, 'visibilitychange', () =>
      document.visibilityState === 'hidden' ? this.flushEngagement() : this.resumeEngagement(),
    );
    this.listen(window, 'pagehide', () => this.flushEngagement());

    if (this.options.autoPageviews) {
      this.patchHistory();
      this.listen(window, 'popstate', () => this.pageview());
      this.pageview();
    }
    this.listenClicks();
    if (this.options.autoEvents.forms) this.listenForms();
  }

  /** Records a page view. Called automatically unless `autoPageviews: false`. */
  pageview(url: string = location.href): void {
    if (!this.enabled) return;
    const clean = url.split('#')[0]!;
    // React StrictMode, replaceState for query tweaks, double effects... one view per URL.
    if (clean === this.currentUrl) return;

    this.flushEngagement();
    this.currentUrl = clean;
    this.pageviewId = randomId();
    this.engagedMs = 0;
    this.visibleSince = document.visibilityState === 'hidden' ? 0 : Date.now();

    const payload = this.base('pageview');
    if (this.firstPageview && document.referrer) payload.r = document.referrer;
    this.firstPageview = false;
    payload.w = window.screen?.width;
    payload.l = navigator.language;
    try {
      payload.tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
      // old browsers, no big deal
    }
    this.send(payload);
  }

  /** Custom event: `track('agregar_al_carrito', { valor: 120000 })`. */
  track(name: string, props?: EventProps): void {
    if (!this.enabled) return;
    const n = String(name ?? '')
      .trim()
      .slice(0, 64);
    if (!n) return;
    const payload = this.base('event');
    payload.n = n;
    const p = sanitizeProps(props);
    if (p) payload.p = p;
    this.send(payload);
  }

  /** Removes listeners and restores history. Mostly for tests and hot reload. */
  destroy(): void {
    this.flushEngagement();
    this.cleanup.forEach((fn) => fn());
    this.cleanup = [];
  }

  private shouldTrack(): boolean {
    if (typeof window === 'undefined' || typeof document === 'undefined') return false; // SSR
    if (!this.options.siteId || !this.options.endpoint) return false;
    if ((navigator as Navigator & { webdriver?: boolean }).webdriver) return false;
    const host = location.hostname;
    if (!this.options.trackLocalhost && (host === 'localhost' || /^127\.|^\[?::1\]?$/.test(host)))
      return false;
    if (this.options.respectDoNotTrack) {
      const nav = navigator as Navigator & { globalPrivacyControl?: boolean };
      const dnt = nav.doNotTrack ?? (window as unknown as { doNotTrack?: string }).doNotTrack;
      if (dnt === '1' || dnt === 'yes' || nav.globalPrivacyControl === true) return false;
    }
    return true;
  }

  private base(e: Payload['e']): Payload {
    return {
      v: 1,
      s: this.options.siteId,
      e,
      u: this.currentUrl || location.href.split('#')[0]!,
      i: this.pageviewId,
    };
  }

  private resumeEngagement(): void {
    if (!this.visibleSince) this.visibleSince = Date.now();
  }

  /** Time on page = time the tab was actually visible, not time it sat in the background. */
  private flushEngagement(): void {
    if (!this.currentUrl) return;
    if (this.visibleSince) {
      this.engagedMs += Date.now() - this.visibleSince;
      this.visibleSince = 0;
    }
    const d = Math.min(Math.round(this.engagedMs), MAX_ENGAGEMENT_MS);
    this.engagedMs = 0;
    if (d < 500) return;
    const payload = this.base('engagement');
    payload.d = d;
    this.send(payload);
  }

  private send(payload: Payload): void {
    const body = JSON.stringify(payload);
    this.log(payload);
    // text/plain keeps it a "simple" request: no CORS preflight, works on shared hosting.
    try {
      if (navigator.sendBeacon?.(this.options.endpoint, new Blob([body], { type: 'text/plain' }))) return;
    } catch {
      // fall through to fetch
    }
    try {
      void fetch(this.options.endpoint, {
        method: 'POST',
        body,
        keepalive: true,
        credentials: 'omit',
        headers: { 'Content-Type': 'text/plain' },
      }).catch(() => undefined);
    } catch {
      // analytics must never break the site
    }
  }

  private patchHistory(): void {
    for (const method of ['pushState', 'replaceState'] as const) {
      const original = history[method];
      const tracker = this;
      history[method] = function (this: History, ...args: Parameters<History['pushState']>) {
        const result = original.apply(this, args);
        // let the router update title/content first
        setTimeout(() => tracker.pageview(), 0);
        return result;
      };
      this.cleanup.push(() => (history[method] = original));
    }
  }

  private listenClicks(): void {
    const auto = this.options.autoEvents;
    this.listen(
      document,
      'click',
      (event) => {
        const target = event.target as Element | null;
        if (!target?.closest) return;

        // <button data-pulso-event="cotizar" data-pulso-plan="pro"> -> track('cotizar', { plan: 'pro' })
        const custom = target.closest<HTMLElement>('[data-pulso-event]');
        if (custom) {
          const props: EventProps = {};
          for (const [key, value] of Object.entries(custom.dataset)) {
            if (key !== 'pulsoEvent' && key.startsWith('pulso') && value !== undefined) {
              const prop = key.slice(5);
              props[prop.charAt(0).toLowerCase() + prop.slice(1)] = value;
            }
          }
          this.track(custom.dataset.pulsoEvent!, props);
          return;
        }

        const link = target.closest<HTMLAnchorElement>('a[href]');
        if (!link) return;
        let url: URL;
        try {
          url = new URL(link.href, location.href);
        } catch {
          return;
        }

        if (url.protocol === 'tel:') {
          if (auto.phone) this.track('phone_click');
        } else if (url.protocol === 'mailto:') {
          if (auto.email) this.track('email_click');
        } else if (url.protocol === 'whatsapp:' || WHATSAPP_HOSTS.test(url.hostname)) {
          if (auto.whatsapp) this.track('whatsapp_click');
        } else if (auto.downloads && DOWNLOAD_EXT.test(url.pathname)) {
          this.track('file_download', { ext: url.pathname.split('.').pop()!.toLowerCase() });
        } else if (
          auto.outbound &&
          /^https?:$/.test(url.protocol) &&
          stripWww(url.hostname) !== stripWww(location.hostname)
        ) {
          this.track('outbound_click', { host: stripWww(url.hostname) });
        }
      },
      true,
    );
  }

  private listenForms(): void {
    this.listen(
      document,
      'submit',
      (event) => {
        const form = event.target as HTMLFormElement | null;
        if (!form || form.tagName !== 'FORM') return;
        const id = form.getAttribute('data-pulso-form') || form.id || form.getAttribute('name') || 'form';
        this.track('form_submit', { form: id });
      },
      true,
    );
  }

  private listen(target: EventTarget, type: string, handler: (e: Event) => void, capture = false): void {
    target.addEventListener(type, handler, capture);
    this.cleanup.push(() => target.removeEventListener(type, handler, capture));
  }

  private log(message: unknown): void {
    if (this.options.debug) console.log('[pulso]', message);
  }
}
