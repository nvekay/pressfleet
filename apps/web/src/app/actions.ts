'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { api, ApiError, type ImportSource } from '@/lib/api';
import type { ImportPreview } from '@/lib/types';

export interface FormState {
  error?: string;
}

const message = (err: unknown) => (err instanceof ApiError ? err.message : 'Something went wrong. Try again.');

const text = (form: FormData, key: string) => {
  const v = form.get(key);
  return typeof v === 'string' ? v.trim() : '';
};

export async function addSite(_prev: FormState, form: FormData): Promise<FormState> {
  try {
    await api.addSite({
      name: text(form, 'name') || undefined,
      url: text(form, 'url'),
      username: text(form, 'username'),
      applicationPassword: text(form, 'applicationPassword'),
      secret: text(form, 'secret'),
    });
  } catch (err) {
    return { error: message(err) };
  }
  revalidatePath('/sites');
  return {};
}

export async function checkSite(id: string): Promise<void> {
  await api.checkSite(id);
  revalidatePath('/sites');
  revalidatePath('/');
}

export type PreviewState = FormState & { preview?: ImportPreview };

/** Parses the import on the API without publishing anything */
export async function previewImport(source: ImportSource): Promise<PreviewState> {
  try {
    return { preview: await api.preview(source) };
  } catch (err) {
    return { error: message(err) };
  }
}

export async function startCampaign(input: ImportSource & { name: string; siteIds: string[] }): Promise<FormState> {
  let id: string;
  try {
    ({ id } = await api.createCampaign(input));
  } catch (err) {
    return { error: message(err) };
  }
  redirect(`/campaigns/${id}`);
}

/** Demo: the site answers 503 to publishing for this many seconds */
export async function simulateOutage(siteId: string, seconds: number): Promise<FormState> {
  try {
    await api.outage(siteId, seconds);
    return {};
  } catch (err) {
    return { error: message(err) };
  }
}

export async function retryFailed(id: string): Promise<void> {
  await api.retryFailed(id);
}
