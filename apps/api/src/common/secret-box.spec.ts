import { randomBytes } from 'node:crypto';
import { SecretBox } from './secret-box.js';

describe('SecretBox', () => {
  const box = new SecretBox(randomBytes(32).toString('base64'));

  it('round-trips and never stores the plain text', () => {
    const sealed = box.seal('abcd efgh ijkl mnop');
    expect(sealed).not.toContain('abcd');
    expect(box.open(sealed)).toBe('abcd efgh ijkl mnop');
  });

  it('uses a fresh IV every time', () => {
    expect(box.seal('same')).not.toBe(box.seal('same'));
  });

  it('refuses tampered data and the wrong key', () => {
    const sealed = Buffer.from(box.seal('secret'), 'base64');
    sealed[sealed.length - 1]! ^= 1;
    expect(() => box.open(sealed.toString('base64'))).toThrow();
    expect(() => new SecretBox(randomBytes(32).toString('base64')).open(box.seal('secret'))).toThrow();
  });
});
