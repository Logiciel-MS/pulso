export type EventProps = Record<string, string | number | boolean>;

export interface AutoEventsOptions {
  /** Clicks on wa.me / api.whatsapp.com links -> 'whatsapp_click'. Default true. */
  whatsapp?: boolean;
  /** Clicks on tel: links -> 'phone_click'. Default true. */
  phone?: boolean;
  /** Clicks on mailto: links -> 'email_click'. Default true. */
  email?: boolean;
  /** Clicks on links to other sites -> 'outbound_click'. Default true. */
  outbound?: boolean;
  /** Clicks on PDFs, zips, etc. -> 'file_download'. Default true. */
  downloads?: boolean;
  /** Form submissions -> 'form_submit' (never the field values). Default true. */
  forms?: boolean;
}

export interface PulsoOptions {
  /** Public id of the site, from `php bin/site-add.php`. */
  siteId: string;
  /** Full URL of your Pulso server collect endpoint, e.g. 'https://analytics.example.com/collect'. */
  endpoint: string;
  /** Track page views automatically, including client-side navigation (React Router, Next.js). Default true. */
  autoPageviews?: boolean;
  /** Track WhatsApp, phone, email, outbound, downloads and forms automatically. Default true (all). */
  autoEvents?: boolean | AutoEventsOptions;
  /** Don't track visitors with Do Not Track / Global Privacy Control on. Default true. */
  respectDoNotTrack?: boolean;
  /** Track on localhost and 127.0.0.1. Default false, so your dev sessions don't pollute the stats. */
  trackLocalhost?: boolean;
  /** Log every payload to the console. */
  debug?: boolean;
}

/** What actually travels to the server. Short keys = smaller beacons. */
export interface Payload {
  v: 1;
  s: string;
  e: 'pageview' | 'event' | 'engagement';
  u: string;
  /** Page view id, ties events and time-on-page to the page they happened on. */
  i: string;
  r?: string;
  n?: string;
  p?: EventProps;
  d?: number;
  w?: number;
  l?: string;
  tz?: string;
}
