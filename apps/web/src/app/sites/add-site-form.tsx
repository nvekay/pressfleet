'use client';

import { useActionState } from 'react';
import { Button } from '@/components/ui/button';
import { addSite, type FormState } from '../actions';

const FIELDS = [
  { name: 'url', label: 'Site URL', placeholder: 'https://example.com', type: 'url', required: true },
  { name: 'name', label: 'Name (optional)', placeholder: 'Taken from the site', type: 'text', required: false },
  { name: 'username', label: 'WordPress user', placeholder: 'admin', type: 'text', required: true },
  {
    name: 'applicationPassword',
    label: 'Application Password',
    placeholder: 'abcd efgh ijkl mnop qrst uvwx',
    type: 'password',
    required: true,
  },
  {
    name: 'secret',
    label: 'PressFleet secret',
    placeholder: 'Settings → PressFleet',
    type: 'password',
    required: true,
  },
] as const;

/** Adds a site; the API checks the connection first, so a wrong password is reported here */
export function AddSiteForm() {
  const [state, action, pending] = useActionState<FormState, FormData>(addSite, {});
  return (
    <form action={action} className="mt-8 rounded-xl border border-ink-200 p-5">
      <h2 className="font-semibold">Add a site</h2>
      <p className="mt-1 text-sm text-ink-600">
        Install the connector plugin, create an Application Password under Users → Profile, and copy the secret from
        Settings → PressFleet.
      </p>
      <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {FIELDS.map((f) => (
          <label key={f.name} className="block text-sm">
            <span className="text-ink-600">{f.label}</span>
            <input
              name={f.name}
              type={f.type}
              required={f.required}
              placeholder={f.placeholder}
              autoComplete="off"
              className="mt-1 h-9 w-full rounded-md border border-ink-200 px-2.5 focus:border-accent focus:ring-2 focus:ring-accent/30 focus:outline-none"
            />
          </label>
        ))}
      </div>
      {state.error && <p className="mt-3 text-sm text-red-700">{state.error}</p>}
      <Button type="submit" variant="primary" className="mt-4" loading={pending}>
        Check and add
      </Button>
    </form>
  );
}
