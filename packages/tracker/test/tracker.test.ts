import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { init, pageview, track } from '../src';
import { sanitizeProps } from '../src/tracker';
import { captureBeacons, ENDPOINT, reset, setUrl } from './helpers';

const SITE = 'gz_test123';

beforeEach(reset);
afterEach(reset);

describe('pageviews', () => {
  it('sends the first page view with context', async () => {
    const { beacon, payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });

    expect(beacon).toHaveBeenCalledTimes(1);
    expect(beacon.mock.calls[0]![0]).toBe(ENDPOINT);
    const [p] = await payloads();
    expect(p).toMatchObject({ v: 1, s: SITE, e: 'pageview', u: 'https://cliente.co/' });
    expect(p!.i).toMatch(/^[a-z0-9]+$/);
    expect(typeof p!.tz).toBe('string');
    expect(typeof p!.l).toBe('string');
  });

  it('sends the beacon as text/plain (no CORS preflight)', () => {
    const { beacon } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    expect((beacon.mock.calls[0]![1] as Blob).type).toContain('text/plain');
  });

  it('follows client-side navigation (pushState) and ignores hash changes', async () => {
    vi.useFakeTimers();
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });

    history.pushState({}, '', '/productos?utm_source=instagram');
    vi.runAllTimers();
    history.pushState({}, '', '/productos?utm_source=instagram#fotos');
    vi.runAllTimers();
    history.replaceState({}, '', '/productos?utm_source=instagram');
    vi.runAllTimers();

    const views = (await payloads()).filter((p) => p.e === 'pageview');
    expect(views.map((v) => v.u)).toEqual([
      'https://cliente.co/',
      'https://cliente.co/productos?utm_source=instagram',
    ]);
  });

  it('only sends the referrer on the first page view', async () => {
    vi.useFakeTimers();
    Object.defineProperty(document, 'referrer', { value: 'https://www.google.com/', configurable: true });
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    history.pushState({}, '', '/contacto');
    vi.runAllTimers();
    const [first, second] = await payloads();
    expect(first!.r).toBe('https://www.google.com/');
    expect(second!.r).toBeUndefined();
    Object.defineProperty(document, 'referrer', { value: '', configurable: true });
  });

  it('popstate (back button) is a page view', async () => {
    vi.useFakeTimers();
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    setUrl('https://cliente.co/nosotros');
    window.dispatchEvent(new PopStateEvent('popstate'));
    expect((await payloads()).map((p) => p.u)).toContain('https://cliente.co/nosotros');
  });

  it('autoPageviews: false leaves it to you', async () => {
    const { beacon, payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT, autoPageviews: false });
    expect(beacon).not.toHaveBeenCalled();
    pageview('https://cliente.co/manual');
    expect((await payloads())[0]!.u).toBe('https://cliente.co/manual');
  });
});

describe('time on page', () => {
  it('sends visible time when navigating away, tied to the previous page view', async () => {
    vi.useFakeTimers();
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    vi.advanceTimersByTime(4000);
    history.pushState({}, '', '/servicios');
    vi.runAllTimers();

    const [first, engagement] = await payloads();
    expect(engagement).toMatchObject({ e: 'engagement', u: 'https://cliente.co/', i: first!.i });
    expect(engagement!.d).toBeGreaterThanOrEqual(4000);
    expect(engagement!.d).toBeLessThan(4100);
  });

  it("doesn't count time while the tab is in the background", async () => {
    vi.useFakeTimers();
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });

    vi.advanceTimersByTime(3000);
    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
    document.dispatchEvent(new Event('visibilitychange'));
    vi.advanceTimersByTime(60_000); // tab in the background for a minute
    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
    document.dispatchEvent(new Event('visibilitychange'));
    vi.advanceTimersByTime(2000);
    window.dispatchEvent(new Event('pagehide'));

    const durations = (await payloads()).filter((p) => p.e === 'engagement').map((p) => p.d!);
    expect(durations).toHaveLength(2);
    expect(durations[0]).toBeGreaterThanOrEqual(3000);
    expect(durations[1]).toBeGreaterThanOrEqual(2000);
    expect(durations[0]! + durations[1]!).toBeLessThan(5200);
  });

  it('ignores blinks under half a second', async () => {
    vi.useFakeTimers();
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    vi.advanceTimersByTime(200);
    window.dispatchEvent(new Event('pagehide'));
    expect((await payloads()).filter((p) => p.e === 'engagement')).toHaveLength(0);
  });
});

describe('when not to track', () => {
  it.each(['http://localhost:5173/', 'http://127.0.0.1:3000/'])('skips %s', (url) => {
    setUrl(url);
    const { beacon } = captureBeacons();
    expect(init({ siteId: SITE, endpoint: ENDPOINT }).enabled).toBe(false);
    track('algo');
    expect(beacon).not.toHaveBeenCalled();
  });

  it('trackLocalhost: true for testing', () => {
    setUrl('http://localhost:5173/');
    const { beacon } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT, trackLocalhost: true });
    expect(beacon).toHaveBeenCalledTimes(1);
  });

  it('respects Do Not Track by default', () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const { beacon } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    expect(beacon).not.toHaveBeenCalled();
  });

  it('...unless told otherwise', () => {
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true });
    const { beacon } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT, respectDoNotTrack: false });
    expect(beacon).toHaveBeenCalledTimes(1);
  });

  it('skips automated browsers (headless Chrome, Playwright, bots)', () => {
    Object.defineProperty(navigator, 'webdriver', { value: true, configurable: true });
    const { beacon } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    expect(beacon).not.toHaveBeenCalled();
  });

  it('needs siteId and endpoint', () => {
    const { beacon } = captureBeacons();
    expect(init({ siteId: '', endpoint: ENDPOINT }).enabled).toBe(false);
    expect(beacon).not.toHaveBeenCalled();
  });
});

describe('custom events', () => {
  it('track() before init is a silent no-op', () => {
    const { beacon } = captureBeacons();
    track('nada');
    expect(beacon).not.toHaveBeenCalled();
  });

  it('sends name and props tied to the current page view', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    track('agregar_al_carrito', { valor: 120000, producto: 'Camisa' });
    const [view, event] = await payloads();
    expect(event).toMatchObject({
      e: 'event',
      n: 'agregar_al_carrito',
      p: { valor: 120000, producto: 'Camisa' },
    });
    expect(event!.i).toBe(view!.i);
  });

  it('ignores empty names', () => {
    const { beacon } = captureBeacons();
    init({ siteId: SITE, endpoint: ENDPOINT });
    track('   ');
    expect(beacon).toHaveBeenCalledTimes(1); // only the page view
  });

  it('sanitizes props', () => {
    const many = Object.fromEntries(Array.from({ length: 15 }, (_, i) => [`k${i}`, i]));
    expect(Object.keys(sanitizeProps(many)!)).toHaveLength(10);
    expect(sanitizeProps({ a: 'x'.repeat(500) })!.a).toHaveLength(256);
    expect(sanitizeProps({ ok: true, bad: { nested: 1 } as never, nan: NaN })).toEqual({ ok: true });
    expect(sanitizeProps({})).toBeUndefined();
  });

  it('init twice returns the same tracker (React StrictMode)', () => {
    const { beacon } = captureBeacons();
    const a = init({ siteId: SITE, endpoint: ENDPOINT });
    const b = init({ siteId: SITE, endpoint: ENDPOINT });
    expect(a).toBe(b);
    expect(beacon).toHaveBeenCalledTimes(1);
  });

  it('falls back to fetch when sendBeacon is not available', () => {
    Object.defineProperty(navigator, 'sendBeacon', { value: undefined, configurable: true, writable: true });
    const fetchMock = vi.fn(() => Promise.resolve(new Response(null, { status: 202 })));
    vi.stubGlobal('fetch', fetchMock);
    init({ siteId: SITE, endpoint: ENDPOINT });
    expect(fetchMock).toHaveBeenCalledWith(
      ENDPOINT,
      expect.objectContaining({ method: 'POST', keepalive: true }),
    );
    vi.unstubAllGlobals();
  });
});
