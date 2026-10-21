/**
 * The public demo shows leads to every visitor, so what people typed into a form is reduced to a hint there:
 * "Jane Doe" → "J*** D***", "jane@example.com" → "j***@example.com", "+351 912 345 678" → "*** 78".
 * The full lead still goes to the owner's Telegram.
 */
export function maskName(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((word) => `${[...word][0]}***`)
    .join(' ');
}

export function maskContact(contact: string): string {
  const value = contact.trim();
  const at = value.lastIndexOf('@');
  if (at > 0) return `${[...value][0]}***${value.slice(at)}`;
  const digits = value.replace(/\D/g, '');
  return digits.length >= 4 ? `*** ${digits.slice(-2)}` : '***';
}

export function maskMessage(message: string): string {
  const length = [...message.trim()].length;
  return length ? `Hidden in the public demo (${length} characters)` : '';
}
