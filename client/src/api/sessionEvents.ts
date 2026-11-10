/**
 * Session lifecycle bridge between the HTTP client and the authentication store.
 *
 * The API client must not import the Pinia store directly: the store already
 * depends on the client, so that would create a circular import. These
 * lightweight registration hooks keep the dependency graph acyclic while
 * guaranteeing that a server-side session termination is reflected in the
 * in-memory client state.
 */

export type UnauthorizedHandler = () => void
export type ForbiddenHandler = (message?: string) => void

let unauthorizedHandler: UnauthorizedHandler | null = null
let forbiddenHandler: ForbiddenHandler | null = null

/**
 * Register a handler invoked when the server reports an unauthenticated session.
 * Returns an unsubscribe function.
 */
export function onUnauthorized(handler: UnauthorizedHandler): () => void {
  unauthorizedHandler = handler
  return () => {
    if (unauthorizedHandler === handler) {
      unauthorizedHandler = null
    }
  }
}

/**
 * Register a handler invoked when the server reports an authorization denial.
 * Returns an unsubscribe function.
 */
export function onForbidden(handler: ForbiddenHandler): () => void {
  forbiddenHandler = handler
  return () => {
    if (forbiddenHandler === handler) {
      forbiddenHandler = null
    }
  }
}

/**
 * Notify the application that the server session is no longer valid.
 */
export function emitUnauthorized(): void {
  unauthorizedHandler?.()
}

/**
 * Notify the application that the request was refused by the authorization layer.
 */
export function emitForbidden(message?: string): void {
  forbiddenHandler?.(message)
}
