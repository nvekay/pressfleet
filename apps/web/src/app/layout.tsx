import type { Metadata } from 'next';
import { Geist, Geist_Mono } from 'next/font/google';
import Link from 'next/link';
import { Logo } from '@/components/logo';
import { NavLink } from '@/components/nav-link';
import './globals.css';

const geist = Geist({ subsets: ['latin'], variable: '--font-geist' });
const geistMono = Geist_Mono({ subsets: ['latin'], variable: '--font-geist-mono' });

export const metadata: Metadata = {
  title: { default: 'PressFleet — publish to every WordPress site at once', template: '%s · PressFleet' },
  description:
    'Bulk publishing to a network of WordPress sites: CSV or Google Sheets in, a queue with retries, a connector plugin with a signed REST API. Demo project.',
};

const GITHUB_URL = process.env.NEXT_PUBLIC_GITHUB_URL;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={`${geist.variable} ${geistMono.variable}`}>
      <body className="flex min-h-dvh flex-col font-sans">
        <header className="border-b border-ink-100">
          <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <Link href="/" className="flex items-center gap-2 font-semibold">
              <Logo />
              PressFleet
              <span className="rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-medium text-ink-600">demo</span>
            </Link>
            <nav className="flex items-center gap-1 text-sm text-ink-600">
              <NavLink href="/">Overview</NavLink>
              <NavLink href="/campaigns">Campaigns</NavLink>
              <NavLink href="/sites">Sites</NavLink>
              {GITHUB_URL && (
                <a
                  href={GITHUB_URL}
                  className="hidden rounded-md px-3 py-1.5 hover:bg-ink-100 hover:text-ink-900 sm:block"
                >
                  Source code
                </a>
              )}
            </nav>
          </div>
        </header>
        <main className="flex-1">{children}</main>
        <footer className="border-t border-ink-100 py-6 text-center text-xs text-ink-500">
          A demo of bulk publishing to WordPress. The sites, brands and posts are fictional.
        </footer>
      </body>
    </html>
  );
}
