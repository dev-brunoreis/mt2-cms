#!/usr/sbin/node
'use strict';

const RULES = [
  { re: /^src\/Admin\/AdminResourceCatalog\.php$/, docs: ['docs/acl.md', 'docs/map.md'] },
  { re: /^src\/Admin\/Admin(Sections|Paths|Permissions)\.php$/, docs: ['docs/acl.md', 'docs/add-admin-section.md', 'docs/map.md'] },
  { re: /^src\/Http\/AdminRoutes\.php$/, docs: ['docs/map.md', 'docs/acl.md'] },
  { re: /^src\/Http\/PublicRoutes\.php$/, docs: ['docs/map.md', 'docs/add-page.md'] },
  { re: /^src\/Http\/Controller\/Admin\/AdminEconomy/, docs: ['docs/economy.md', 'docs/acl.md'] },
  { re: /^src\/Http\/Controller\/Admin\/Admin(Payment|CashPackage)/, docs: ['docs/payments.md', 'docs/acl.md'] },
  { re: /^src\/Http\/Controller\/(Donate|PaymentWebhook)/, docs: ['docs/payments.md'] },
  { re: /^src\/Http\/Controller\/SeoController\.php$/, docs: ['docs/seo.md'] },
  { re: /^src\/Http\/Controller\/Admin\//, docs: ['docs/acl.md', 'docs/add-admin-section.md'] },
  { re: /^src\/Http\/Controller\//, docs: ['docs/add-page.md'] },
  { re: /^src\/Admin\/Grid\//, docs: ['docs/add-admin-section.md'] },
  { re: /^src\/Payment\/|^src\/Service\/Payment|^src\/Service\/CashCredit|^bin\/payments-/, docs: ['docs/payments.md'] },
  { re: /^src\/Service\/Economy|^src\/Repository\/(Economy|GameEconomy)|^bin\/economy-/, docs: ['docs/economy.md'] },
  { re: /^src\/Service\/Notification|^src\/Repository\/Notification/, docs: ['docs/notifications.md'] },
  { re: /^src\/Service\/Seo/, docs: ['docs/seo.md'] },
  { re: /^src\/Repository\//, docs: ['docs/add-repository.md'] },
  { re: /^src\/Setup\/migrations\//, docs: ['docs/map.md'] },
  { re: /^themes\/admin\//, docs: ['docs/add-admin-section.md', 'docs/acl.md'] },
  { re: /^themes\//, docs: ['docs/add-theme.md'] },
  { re: /^lang\//, docs: ['docs/add-locale.md'] },
  { re: /^game\//, docs: ['docs/game-files.md'] },
  { re: /^tests\/Unit\/Contract\//, docs: ['docs/security.md', 'docs/acl.md', 'docs/testing.md'] },
  { re: /^tests\//, docs: ['docs/testing.md'] },
];

function collectPaths(value, out) {
  if (value == null) {
    return;
  }
  if (typeof value === 'string') {
    if (value.includes('/') || /\.(php|twig|sql|json|js|md|mdc)$/.test(value)) {
      out.push(value);
    }
    return;
  }
  if (Array.isArray(value)) {
    for (const item of value) {
      collectPaths(item, out);
    }
    return;
  }
  if (typeof value === 'object') {
    for (const [key, item] of Object.entries(value)) {
      if (/^(contents|diff|old_string|new_string|prompt)$/i.test(key)) {
        continue;
      }
      collectPaths(item, out);
    }
  }
}

function normalize(filePath) {
  let path = String(filePath).replace(/\\/g, '/');
  const markers = ['/mt2-cms/', 'mt2-cms/'];
  for (const marker of markers) {
    const idx = path.indexOf(marker);
    if (idx !== -1) {
      path = path.slice(idx + marker.length);
      break;
    }
  }
  path = path.replace(/^\.\//, '');
  if (path.startsWith('file://')) {
    path = path.slice('file://'.length);
  }
  return path;
}

function docsFor(path) {
  for (const rule of RULES) {
    if (rule.re.test(path)) {
      return rule.docs;
    }
  }
  return [];
}

let raw = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  raw += chunk;
});
process.stdin.on('end', () => {
  let payload = {};
  try {
    payload = raw.trim() ? JSON.parse(raw) : {};
  } catch {
    process.stdout.write('{}\n');
    return;
  }

  const found = [];
  collectPaths(payload, found);

  const needed = [];
  for (const file of found) {
    const path = normalize(file);
    if (path.startsWith('docs/') || path.startsWith('.cursor/')) {
      continue;
    }
    for (const doc of docsFor(path)) {
      if (!needed.includes(doc)) {
        needed.push(doc);
      }
    }
  }

  if (needed.length === 0) {
    process.stdout.write('{}\n');
    return;
  }

  process.stdout.write(JSON.stringify({
    additional_context: 'Edited code that maps to docs. If behavior changed, update: '
      + needed.join(', ')
      + ' (and the matching row in docs/map.md). If behavior changed, add or adjust a unit test — docs/testing.md. Admin POSTs need ACL — docs/acl.md.',
  }));
});
