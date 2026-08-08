import type { InertiaLinkProps } from '@inertiajs/vue3'
import { clsx } from 'clsx'
import type { ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
  return typeof href === 'string' ? href : href?.url
}

/**
 * Serialize a params record into a query string (`?a=b&c=d`), dropping empty,
 * null and undefined values. Returns `''` when nothing is left.
 */
export function toQuery(
  params: Record<string, string | number | boolean | null | undefined>,
): string {
  const search = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, String(value))
    }
  })

  const queryString = search.toString()

  return queryString ? `?${queryString}` : ''
}
