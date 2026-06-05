const fs = require('fs')

for (const file of ['package-lock.json', 'yarn.lock']) {
  try {
    fs.rmSync(file, { force: true })
  } catch (error) {
    console.error(`Failed to remove ${file}: ${error.message}`)
    process.exit(1)
  }
}

const userAgent = process.env.npm_config_user_agent || ''
if (!userAgent.startsWith('pnpm/')) {
  console.error('Use pnpm instead')
  process.exit(1)
}
