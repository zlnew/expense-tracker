import { beforeEach, describe, expect, it } from 'vitest'
import { updateTheme } from '../useAppearance'

describe('useAppearance: updateTheme', () => {
  beforeEach(() => {
    document.documentElement.className = ''
    document.documentElement.style.colorScheme = ''

    // Clean up existing meta tags
    document
      .querySelectorAll('meta[name="theme-color"]')
      .forEach((el) => el.remove())

    // Create realistic meta tags as in app.blade.php
    const metaLight = document.createElement('meta')
    metaLight.setAttribute('name', 'theme-color')
    metaLight.setAttribute('media', '(prefers-color-scheme: light)')
    metaLight.setAttribute('content', '#faf9f7')
    document.head.appendChild(metaLight)

    const metaDark = document.createElement('meta')
    metaDark.setAttribute('name', 'theme-color')
    metaDark.setAttribute('media', '(prefers-color-scheme: dark)')
    metaDark.setAttribute('content', '#14131b')
    document.head.appendChild(metaDark)

    const metaFallback = document.createElement('meta')
    metaFallback.setAttribute('name', 'theme-color')
    metaFallback.setAttribute('content', '#faf9f7')
    document.head.appendChild(metaFallback)
  })

  it('updates all meta theme-color tags and colorScheme on dark mode', () => {
    updateTheme('dark')

    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(document.documentElement.style.colorScheme).toBe('dark')

    const metas = document.querySelectorAll('meta[name="theme-color"]')
    expect(metas.length).toBe(3)
    metas.forEach((meta) => {
      expect(meta.getAttribute('content')).toBe('#14131b')
    })
  })

  it('updates all meta theme-color tags and colorScheme on light mode', () => {
    // First set dark
    updateTheme('dark')

    // Then switch to light
    updateTheme('light')

    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(document.documentElement.style.colorScheme).toBe('light')

    const metas = document.querySelectorAll('meta[name="theme-color"]')
    expect(metas.length).toBe(3)
    metas.forEach((meta) => {
      expect(meta.getAttribute('content')).toBe('#faf9f7')
    })
  })
})
