import { describe, expect, it } from 'vitest';
import { parseDate, parseImport, sheetCsvUrl, slugify, splitList, toHtml } from './import.js';

const csv = (...lines: string[]) => lines.join('\n');

describe('parseImport', () => {
  it('turns a row into a post payload', () => {
    const { rows, problems } = parseImport(
      csv(
        'id,title,content,categories,tags,image_url,image_alt,seo_title,seo_description,publish_at,sites',
        'p-1,Desk stretches,"Sit **less**.",Guides|News,"posture, office",https://cdn.test/a.jpg,Cover,Stretches,Five stretches,2026-11-01 09:00,alder|birch',
      ),
    );
    expect(problems).toEqual([]);
    expect(rows).toEqual([
      {
        row: 1,
        sites: ['alder', 'birch'],
        payload: {
          external_id: 'p-1',
          title: 'Desk stretches',
          content: '<p>Sit <strong>less</strong>.</p>',
          categories: ['Guides', 'News'],
          tags: ['posture', 'office'],
          featured_image: { url: 'https://cdn.test/a.jpg', alt: 'Cover' },
          seo: { title: 'Stretches', description: 'Five stretches' },
          date: '2026-11-01T09:00:00Z',
        },
      },
    ]);
  });

  it('uses the slug of the title when there is no id column, so a re-import updates the same posts', () => {
    const { rows } = parseImport(csv('title,content', 'Five Stretches for Desk Workers!,Text'));
    expect(rows[0]?.payload.external_id).toBe('five-stretches-for-desk-workers');
  });

  it('keeps HTML content as it is', () => {
    const { rows } = parseImport(csv('title,content', 'T,"<h2>Hi</h2><p>There</p>"'));
    expect(rows[0]?.payload.content).toBe('<h2>Hi</h2><p>There</p>');
  });

  it('reports every bad row and keeps the good ones', () => {
    const { rows, problems } = parseImport(
      csv(
        'id,title,content,publish_at,image_url,status',
        'a,Good,Text,,,',
        'b,,Text,,,',
        'c,No date,Text,next friday,,',
        'a,Duplicate id,Text,,,',
        'd,Bad image,Text,,ftp://x/y.jpg,',
        'e,Bad status,Text,,,live',
        'has space,Bad id,Text,,,',
      ),
    );
    expect(rows.map((r) => r.payload.external_id)).toEqual(['a']);
    expect(problems).toEqual([
      { row: 2, message: 'Title is empty' },
      { row: 3, message: '"next friday" is not a date (use 2026-11-01 09:00, UTC)' },
      { row: 4, message: 'Same ID "a" as row 1' },
      { row: 5, message: 'Image URL "ftp://x/y.jpg" is not an http(s) link' },
      { row: 6, message: 'Status "live" is not one of publish, draft, pending, private' },
      { row: 7, message: 'ID "has space" may only contain letters, digits, ".", "_", ":" and "-"' },
    ]);
  });

  it('needs title and content columns, and lists columns it does not use', () => {
    expect(parseImport(csv('name,body', 'x,y')).problems).toEqual([
      { row: 0, message: 'The file needs "title" and "content" columns' },
    ]);
    expect(parseImport(csv('Title,Content,Author', 'x,y,z')).unknownColumns).toEqual(['author']);
  });

  it('treats "all" or an empty sites cell as every selected site', () => {
    const { rows } = parseImport(csv('title,content,sites', 'A,x,all', 'B,x,'));
    expect(rows.map((r) => r.sites)).toEqual([null, null]);
  });

  it('reads a UTF-8 file with a BOM, as Excel saves it', () => {
    expect(parseImport('﻿title,content\nA,x').rows).toHaveLength(1);
  });
});

describe('helpers', () => {
  it('slugify', () => {
    expect(slugify('Café & Crème — 2026')).toBe('cafe-creme-2026');
  });

  it('splitList', () => {
    expect(splitList(' a | b ,a;; c ')).toEqual(['a', 'b', 'c']);
    expect(splitList(undefined)).toEqual([]);
  });

  it('parseDate accepts ISO and plain UTC dates', () => {
    expect(parseDate('2026-11-01')).toBe('2026-11-01T00:00:00Z');
    expect(parseDate('2026-11-01T09:30:00+02:00')).toBe('2026-11-01T07:30:00Z');
    expect(parseDate('')).toBeUndefined();
    expect(() => parseDate('01/11/2026')).toThrow();
  });

  it('toHtml renders Markdown', () => {
    expect(toHtml('# Title\n\n- one\n- two')).toBe('<h1>Title</h1>\n<ul>\n<li>one</li>\n<li>two</li>\n</ul>');
  });

  it('sheetCsvUrl keeps the tab', () => {
    expect(sheetCsvUrl('https://docs.google.com/spreadsheets/d/abc_123/edit?gid=42#gid=42')).toBe(
      'https://docs.google.com/spreadsheets/d/abc_123/export?format=csv&gid=42',
    );
    expect(sheetCsvUrl('https://docs.google.com/spreadsheets/d/abc/edit')).toContain('gid=0');
    expect(() => sheetCsvUrl('https://example.com/sheet')).toThrow('Google Sheet');
  });
});
