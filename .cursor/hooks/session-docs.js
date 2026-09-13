#!/usr/sbin/node
'use strict';

let raw = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  raw += chunk;
});
process.stdin.on('end', () => {
  process.stdout.write(JSON.stringify({
      additional_context: [
        'Architecture index: docs/map.md. Code patterns: docs/patterns.md. Tests: docs/testing.md. Read those before grepping the repo.',
        'When behavior changes, update the map + linked doc and add/adjust a tests/Unit test in the same turn.',
        'Admin mutations need ACL: docs/acl.md. Keep SecurityContractTest green.',
      ].join(' '),
  }));
});
