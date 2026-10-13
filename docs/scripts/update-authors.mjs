// Refresh docs/data/authors.json from the public GitHub API.
// Run: node scripts/update-authors.mjs (set GITHUB_TOKEN to avoid the anonymous rate limit).
import { writeFileSync } from 'node:fs'

const repo = 'tomaj/hermes'
const maintainer = 'tomaj'
// Automated accounts (bots and integrations) are not listed as people.
const automated = /\[bot\]$|-badger$|agent$/i

const headers = { Accept: 'application/vnd.github+json', 'User-Agent': 'hermes-docs' }
if (process.env.GITHUB_TOKEN) headers.Authorization = `Bearer ${process.env.GITHUB_TOKEN}`

async function get(path) {
  const response = await fetch(`https://api.github.com/${path}`, { headers })
  if (!response.ok) throw new Error(`${path}: ${response.status}`)
  return response.json()
}

const user = await get(`users/${maintainer}`)
const contributors = (await get(`repos/${repo}/contributors?per_page=100`))
  .filter((c) => c.type === 'User' && !automated.test(c.login))
  .map((c) => ({ login: c.login, avatar: `${c.avatar_url}&s=96`, url: c.html_url, commits: c.contributions }))

const data = {
  maintainer: {
    login: user.login,
    name: user.name,
    avatar: `${user.avatar_url}&s=192`,
    url: user.html_url,
    company: user.company,
    location: user.location,
    // The profile lists http://www.tomaj.sk/, which does not answer; the same site is served over HTTPS.
    blog: user.blog && user.blog.replace(/^http:\/\/(www\.)?/, 'https://'),
    twitter: user.twitter_username,
    githubSince: user.created_at.slice(0, 4),
  },
  contributors,
}
writeFileSync(new URL('../data/authors.json', import.meta.url), JSON.stringify(data, null, 2) + '\n')
console.log(`${contributors.length} contributors written`)
