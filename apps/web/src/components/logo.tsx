/** Three stacked pages: one post, many sites */
export function Logo({ className = 'size-6' }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden>
      <rect x="8" y="2.5" width="12" height="15" rx="2.5" className="fill-accent/30" />
      <rect x="6" y="4.5" width="12" height="15" rx="2.5" className="fill-accent/60" />
      <rect x="4" y="6.5" width="12" height="15" rx="2.5" className="fill-accent" />
      <path d="M7 11h6M7 14h6M7 17h4" stroke="white" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  );
}
