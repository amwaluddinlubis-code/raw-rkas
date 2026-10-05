/* Density ownership QA.
   Density tokens are owned by resources/css/theme-profiles.css. Any other
   stylesheet that *assigns* them pins the active theme density, because it is
   imported later in the cascade. Consumers (token-native-components.css) must
   only read them via var(). These checks fail the build when ownership drifts,
   and when table cell padding is hardcoded instead of using
   --profile-table-row-y. */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const cssDirectory = path.join(projectRoot, 'resources', 'css');
const densityOwner = 'theme-profiles.css';

const densityTokens = [
    '--profile-section-gap',
    '--profile-control-height',
    '--profile-table-row-y',
];

const walk = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(directory, entry.name);

    if (entry.isDirectory()) {
        return walk(fullPath);
    }

    return entry.isFile() && entry.name.endsWith('.css') ? [fullPath] : [];
});

const violations = [];

for (const file of walk(cssDirectory)) {
    const relative = path.relative(projectRoot, file);
    const contents = fs.readFileSync(file, 'utf8');
    const isOwner = path.basename(file) === densityOwner;

    contents.split('\n').forEach((line, index) => {
        if (/^\s*(\/\*|\*|\/\/)/.test(line)) {
            return;
        }

        // Only an assignment (--token: value) counts; var(--token) is a read.
        const declaration = line.match(/^\s*(--profile-[\w-]+)\s*:/);

        if (!declaration || !densityTokens.includes(declaration[1])) {
            return;
        }

        if (isOwner) {
            return;
        }

        violations.push({
            rule: 'density-token-redefinition',
            location: `${relative}:${index + 1}`,
            detail: `${declaration[1]} assigned outside ${densityOwner}; this pins the active theme density`,
        });
    });

    const cellPadding = /padding(?:-block|-top|-bottom)?\s*:\s*([^;{}]*)/g;

    for (const match of contents.matchAll(cellPadding)) {
        const value = match[1].trim();

        if (value.includes('--profile-table-row-y')) {
            continue;
        }

        const selectorBefore = contents.slice(Math.max(0, match.index - 320), match.index);
        const selector = (selectorBefore.split(/\n/).pop() ?? '').trim();

        if (!/\b(th|td)\b/.test(selector)) {
            continue;
        }

        violations.push({
            rule: 'hardcoded-table-cell-padding',
            location: `${relative}:${contents.slice(0, match.index).split('\n').length}`,
            detail: `table cell padding "${value}" should use --profile-table-row-y`,
        });
    }
}

const grouped = violations.reduce((accumulator, violation) => {
    accumulator[violation.rule] ??= [];
    accumulator[violation.rule].push(violation);
    return accumulator;
}, {});

console.log('Density ownership QA');
console.log('');

if (violations.length === 0) {
    console.log('  PASS density tokens assigned only by theme-profiles.css.');
    console.log('  PASS no hardcoded table cell padding.');
} else {
    for (const [rule, items] of Object.entries(grouped)) {
        console.log(`  FAIL ${rule} (${items.length})`);
        items.forEach((item) => console.log(`    ${item.location}  ${item.detail}`));
    }
}

console.log('');

if (violations.length > 0) {
    console.error('Density QA failed.');
    process.exitCode = 1;
} else {
    console.log('All density ownership checks passed.');
}