'use client';

import { PlugZap } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { simulateOutage } from '@/app/actions';

const SECONDS = 60;

/** Takes a site "offline" for a minute: its publishing endpoint answers 503, as a host that went down */
export function OutageButton({
  siteId,
  siteName,
  compact = false,
}: {
  siteId: string;
  siteName: string;
  compact?: boolean;
}) {
  const [until, setUntil] = useState<number | null>(null);
  const [now, setNow] = useState(() => Date.now());
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const left = until ? Math.max(0, Math.ceil((until - now) / 1000)) : 0;

  useEffect(() => {
    if (!until) return;
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, [until]);

  const click = () =>
    start(async () => {
      const result = await simulateOutage(siteId, left ? 0 : SECONDS);
      if (result.error) return setError(result.error);
      setError(null);
      setUntil(left ? null : Date.now() + SECONDS * 1000);
      setNow(Date.now());
    });

  return (
    <button
      type="button"
      onClick={click}
      disabled={pending}
      title={error ?? (left ? 'Bring the site back now' : `Make ${siteName} answer 503 for ${SECONDS} seconds`)}
      className={`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium disabled:opacity-60 ${
        left
          ? 'bg-red-100 text-red-800 hover:bg-red-200'
          : 'border border-ink-200 bg-white text-ink-700 hover:bg-ink-50'
      } ${error ? 'ring-1 ring-red-400' : ''}`}
    >
      <PlugZap className="size-3.5" />
      {left
        ? `${compact ? '' : `${siteName} `}offline ${left} s`
        : compact
          ? 'Take offline'
          : `Take ${siteName} offline`}
    </button>
  );
}
