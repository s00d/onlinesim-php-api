import { readdirSync } from 'node:fs'

const repo = 'onlinesim-php-api'
const docsBase = process.env.NUXT_APP_BASE_URL || (process.env.NODE_ENV === 'production' ? `/${repo}/` : '/')

/** Every content page, so prerender does not depend on link crawling alone. */
const pageRoutes = readdirSync(new URL('./content', import.meta.url))
  .filter((file) => file.endsWith('.md'))
  .map((file) => (file === 'index.md' ? '/' : `/${file.slice(0, -'.md'.length)}`))

export default defineNuxtConfig({
  modules: ['@nuxt/ui', '@nuxt/content', 'nuxt-llms'],

  css: ['~/assets/css/main.css'],

  app: {
    baseURL: docsBase,
    head: {
      htmlAttrs: { lang: 'en' },
      link: [{ rel: 'icon', type: 'image/svg+xml', href: `${docsBase}favicon.svg` }],
    },
  },

  icon: {
    // Bundle every rendered icon: the static host never falls back to the Iconify API.
    clientBundle: {
      scan: true,
      icons: ['simple-icons:packagist', 'simple-icons:composer', 'simple-icons:php', 'lucide:terminal'],
      sizeLimitKb: 256,
    },
  },

  colorMode: {
    preference: 'system',
  },

  content: {
    build: {
      markdown: {
        toc: { searchDepth: 2 },
        highlight: { langs: ['php', 'bash', 'json'] },
      },
    },
    experimental: {
      sqliteConnector: 'native',
    },
  },

  nitro: {
    prerender: {
      // `numbers.html`, not `numbers/index.html`: GitHub Pages serves `/numbers` without a redirect.
      autoSubfolderIndex: false,
      crawlLinks: true,
      failOnError: true,
      routes: pageRoutes,
    },
  },

  llms: {
    domain: `https://s00d.github.io/${repo}`,
    title: 'onlinesim-php-api',
    description: 'PHP 8.1+ client for the OnlineSim API, on ext-curl with no runtime dependencies.',
    sections: [
      {
        title: 'Documentation',
        contentCollection: 'docs',
        contentFilters: [{ field: 'extension', operator: '=', value: 'md' }],
      },
    ],
    full: {
      title: 'onlinesim-php-api — full docs',
      description: 'Complete documentation of the onlinesim-php-api SDK.',
    },
  },

  compatibilityDate: '2025-11-03',
})
