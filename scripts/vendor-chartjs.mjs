import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const source = join(root, 'node_modules', 'chart.js', 'dist', 'chart.umd.min.js')
const licenseCandidates = [
  join(root, 'node_modules', 'chart.js', 'LICENSE.md'),
  join(root, 'node_modules', 'chart.js', 'LICENSE'),
]
const targetDir = join(root, 'public', 'vendor', 'chartjs')

if (!existsSync(source)) {
  console.error('Run npm ci before vendor:chartjs')
  process.exit(1)
}

rmSync(targetDir, { recursive: true, force: true })
mkdirSync(targetDir, { recursive: true })
cpSync(source, join(targetDir, 'chart.umd.min.js'))

for (const license of licenseCandidates) {
  if (existsSync(license)) {
    cpSync(license, join(targetDir, 'LICENSE.md'))
    break
  }
}

console.log('Vendored Chart.js to public/vendor/chartjs')
