/**
 * URLs for files the API serves directly — avatars and other uploads — which
 * are not API calls and so do not go through the `$api` client's baseURL.
 *
 * Auto-imported (`src/utils/` is in the unplugin-auto-import dirs list).
 */

/**
 * The origin the backend serves uploads from.
 *
 * Derived from `VITE_API_BASE_URL` rather than configured separately: uploads
 * come off the same host as the API, and a second variable is a second thing to
 * get wrong. The `/api` suffix is trimmed because upload paths are relative to
 * the document root, not to the API prefix.
 *
 * @returns {string} origin without a trailing slash, or '' when the base URL is
 *   already relative (dev proxy), where a relative asset path resolves anyway.
 */
const uploadsOrigin = () => {
  const base = import.meta.env.VITE_API_BASE_URL || ''

  if (!base.startsWith('http')) return ''

  return base.replace(/\/api\/?$/, '').replace(/\/$/, '')
}

/**
 * Absolute URL for a file path returned by the API.
 *
 * @param {string|null|undefined} path - e.g. `uploads/avatars/3.png`
 * @returns {string} the URL, or '' when there is no path
 *
 * @example
 * assetUrl(user.img) // 'https://api.example.at/uploads/avatars/3.png'
 */
export const assetUrl = path => {
  if (!path) return ''
  if (/^(https?:)?\/\//.test(path) || path.startsWith('data:')) return path

  return `${uploadsOrigin()}/${String(path).replace(/^\//, '')}`
}
