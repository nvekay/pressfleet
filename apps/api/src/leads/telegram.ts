import { env } from '../config/env.js';

const escape = (s: string) => s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c]!);

/** Posts a message to the configured Telegram chat; returns false when Telegram is not configured */
export async function notifyTelegram(lead: {
  site: string;
  name: string;
  contact: string;
  message: string;
  page: string;
}): Promise<boolean> {
  const { TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID } = env();
  if (!TELEGRAM_BOT_TOKEN || !TELEGRAM_CHAT_ID) return false;
  const text = [
    `<b>New lead · ${escape(lead.site)}</b>`,
    `${escape(lead.name)} · ${escape(lead.contact)}`,
    lead.message ? `\n${escape(lead.message.slice(0, 1500))}` : '',
    lead.page ? `\n<i>${escape(lead.page)}</i>` : '',
  ]
    .filter(Boolean)
    .join('\n');
  const res = await fetch(`https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ chat_id: TELEGRAM_CHAT_ID, text, parse_mode: 'HTML', disable_web_page_preview: true }),
    signal: AbortSignal.timeout(10_000),
  });
  if (!res.ok) throw new Error(`Telegram answered ${res.status}: ${(await res.text()).slice(0, 200)}`);
  return true;
}
