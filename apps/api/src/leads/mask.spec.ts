import { describe, expect, it } from 'vitest';
import { maskContact, maskMessage, maskName } from './mask.js';

describe('demo lead masking', () => {
  it('keeps the first letter of each name', () => {
    expect(maskName('  Jane   Doe ')).toBe('J*** D***');
    expect(maskName('Đặng Thu')).toBe('Đ*** T***');
  });

  it('keeps the domain of an email and the last digits of a phone', () => {
    expect(maskContact('jane@example.com')).toBe('j***@example.com');
    expect(maskContact('+351 912 345 678')).toBe('*** 78');
    expect(maskContact('call me')).toBe('***');
  });

  it('hides the message and gives its length', () => {
    expect(maskMessage('Hello there')).toBe('Hidden in the public demo (11 characters)');
    expect(maskMessage(' ')).toBe('');
  });
});
