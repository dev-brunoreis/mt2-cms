import { cpSync, existsSync, rmSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const source = join(root, 'node_modules', 'tinymce')
const target = join(root, 'public', 'vendor', 'tinymce')

if (!existsSync(source)) {
  console.error('Run npm ci before vendor:tinymce')
  process.exit(1)
}

if (existsSync(target)) {
  rmSync(target, { recursive: true, force: true })
}

cpSync(source, target, { recursive: true })
console.log('Vendored TinyMCE to public/vendor/tinymce')
