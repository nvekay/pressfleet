import { ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import { slugify } from '@pressfleet/shared';
import { asc, eq } from 'drizzle-orm';
import { newId } from '../common/ids.js';
import { SecretBox } from '../common/secret-box.js';
import { env } from '../config/env.js';
import { type Db, InjectDb } from '../db/db.module.js';
import { type Site, sites } from '../db/schema.js';
import { type SiteConnection, WpClient, WpError } from '../wordpress/wp-client.js';

export interface NewSite {
  name?: string;
  url: string;
  username: string;
  applicationPassword: string;
  secret: string;
}

/** What the API returns about a site: never the credentials */
export type PublicSite = Omit<Site, 'credentials'>;

export const publicSite = ({ credentials: _credentials, ...site }: Site): PublicSite => site;

@Injectable()
export class SitesService {
  constructor(
    @InjectDb() private readonly db: Db,
    private readonly box: SecretBox,
  ) {}

  list(): Promise<Site[]> {
    return this.db.select().from(sites).orderBy(asc(sites.createdAt));
  }

  async get(id: string): Promise<Site> {
    const [site] = await this.db.select().from(sites).where(eq(sites.id, id));
    if (!site) throw new NotFoundException('Site not found');
    return site;
  }

  /** Decrypted credentials for the publishing worker */
  connection(site: Site): SiteConnection {
    const { applicationPassword, secret } = JSON.parse(this.box.open(site.credentials)) as {
      applicationPassword: string;
      secret: string;
    };
    return { url: site.url, username: site.username, applicationPassword, secret };
  }

  client(site: Site): WpClient {
    return new WpClient(this.connection(site), env().WP_TIMEOUT_MS);
  }

  /**
   * Adds a site after a successful health check, so a typo in the password or a missing plugin is caught here
   * and not in the middle of a campaign. Adding a known URL updates its credentials.
   */
  async add(input: NewSite): Promise<Site> {
    const url = new URL(input.url).origin + new URL(input.url).pathname.replace(/\/+$/, '');
    const connection: SiteConnection = { ...input, url };
    let health;
    try {
      health = await new WpClient(connection, env().WP_TIMEOUT_MS).health();
    } catch (err) {
      const hint =
        err instanceof WpError && err.status === 404
          ? ' Is the PressFleet Connector plugin active?'
          : err instanceof WpError && err.status === 401
            ? ' Check the username, the Application Password and the secret.'
            : '';
      throw new ConflictException(`Could not connect: ${(err as Error).message}.${hint}`);
    }

    const name = input.name?.trim() || health.site;
    const values = {
      name,
      slug: slugify(name),
      url,
      username: input.username,
      credentials: this.box.seal(
        JSON.stringify({ applicationPassword: input.applicationPassword, secret: input.secret }),
      ),
      health,
      healthStatus: 'ok' as const,
      healthError: null,
      checkedAt: new Date(),
    };
    const [site] = await this.db
      .insert(sites)
      .values({ id: newId('site'), ...values })
      .onConflictDoUpdate({ target: sites.url, set: values })
      .returning();
    return site!;
  }

  /** Calls the site's health endpoint and stores the result */
  async check(id: string): Promise<Site> {
    const site = await this.get(id);
    let patch: Partial<Site>;
    try {
      patch = { health: await this.client(site).health(), healthStatus: 'ok', healthError: null };
    } catch (err) {
      patch = { healthStatus: 'error', healthError: (err as Error).message };
    }
    const [updated] = await this.db
      .update(sites)
      .set({ ...patch, checkedAt: new Date() })
      .where(eq(sites.id, id))
      .returning();
    return updated!;
  }

  async remove(id: string): Promise<void> {
    await this.db.delete(sites).where(eq(sites.id, id));
  }
}
