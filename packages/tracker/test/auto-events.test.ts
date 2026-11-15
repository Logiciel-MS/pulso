import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { init } from '../src';
import { captureBeacons, ENDPOINT, reset } from './helpers';

beforeEach(reset);
afterEach(reset);

function click(html: string) {
  document.body.innerHTML = html;
  const el = document.body.querySelector('[data-click]') ?? document.body.firstElementChild!;
  // links in tests shouldn't navigate
  el.addEventListener('click', (e) => e.preventDefault());
  (el as HTMLElement).click();
}

async function events(payloads: () => Promise<{ e: string; n?: string; p?: unknown }[]>) {
  return (await payloads()).filter((p) => p.e === 'event').map((p) => ({ n: p.n, p: p.p }));
}

describe('auto events', () => {
  it.each([
    ['<a href="https://wa.me/573001234567">Escríbenos</a>', 'whatsapp_click'],
    ['<a href="https://api.whatsapp.com/send?phone=573001234567">WA</a>', 'whatsapp_click'],
    ['<a href="tel:+573001234567">Llámanos</a>', 'phone_click'],
    ['<a href="mailto:hola@cliente.co">Correo</a>', 'email_click'],
  ])('%s -> %s, without sending the number or the email', async (html, name) => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    click(html);
    expect(await events(payloads)).toEqual([{ n: name, p: undefined }]);
  });

  it('clicks inside the link count too', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    click('<a href="https://wa.me/57300"><span data-click>Icono</span></a>');
    expect((await events(payloads))[0]!.n).toBe('whatsapp_click');
  });

  it('outbound links send only the host', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    click('<a href="https://www.instagram.com/cliente?igsh=abc">IG</a>');
    expect(await events(payloads)).toEqual([{ n: 'outbound_click', p: { host: 'instagram.com' } }]);
  });

  it('internal links, www and relative links are not outbound', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    click('<a href="/contacto">Contacto</a>');
    click('<a href="https://www.cliente.co/blog">Blog</a>');
    expect(await events(payloads)).toEqual([]);
  });

  it('downloads', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    click('<a href="/docs/Catalogo-2026.PDF">Catálogo</a>');
    expect(await events(payloads)).toEqual([{ n: 'file_download', p: { ext: 'pdf' } }]);
  });

  it('data-pulso-event: custom events without writing JS', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    click(
      '<button data-pulso-event="cotizar" data-pulso-plan="pro" data-pulso-origen-boton="hero">Cotizar</button>',
    );
    expect(await events(payloads)).toEqual([{ n: 'cotizar', p: { plan: 'pro', origenBoton: 'hero' } }]);
  });

  it('form submissions send the form id, never the values', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT });
    document.body.innerHTML =
      '<form id="contacto"><input name="email" value="secreto@correo.com"><button>Enviar</button></form>';
    const form = document.querySelector('form')!;
    form.addEventListener('submit', (e) => e.preventDefault());
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    const sent = await events(payloads);
    expect(sent).toEqual([{ n: 'form_submit', p: { form: 'contacto' } }]);
    expect(JSON.stringify(await payloads())).not.toContain('secreto');
  });

  it('autoEvents: false turns everything off', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT, autoEvents: false });
    click('<a href="https://wa.me/57300">WA</a>');
    expect(await events(payloads)).toEqual([]);
  });

  it('autoEvents can be picked one by one', async () => {
    const { payloads } = captureBeacons();
    init({ siteId: 's', endpoint: ENDPOINT, autoEvents: { outbound: false } });
    click('<a href="https://www.instagram.com/x">IG</a>');
    click('<a href="https://wa.me/57300">WA</a>');
    expect((await events(payloads)).map((e) => e.n)).toEqual(['whatsapp_click']);
  });
});
