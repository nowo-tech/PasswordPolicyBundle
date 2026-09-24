# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/password-policy-bundle` (`symfony-bundle`) |
| Audited revision | `v1.4.4` (base `v1.4.3` / `dc885f9` + remediations) |
| Audit date | 2026-09-23 (remediation closed 2026-09-24) |
| Method | Manual review of every file under `src/` (Doctrine entity listener, kernel request listener, expiry / policy / history / configuration services, flash throttle storages, validator, route matcher, trait, DI extension, `Resources/config/services.yml`) |
| **Verdict** | ✅ **Viable under scenario B** (after remediation) — no bundle service keeps per-request state across requests, the Carbon global locale is no longer touched, and the expiry listener ignores requests that did not pass a firewall. Residual framework responsibility: a stale token on `security: false` firewalls (see W-04) |
| Remediation (2026-09-23/24) | W-01, W-02, W-03, W-04 resolved; W-05 accepted. Shipped in **1.4.4**. Regression tests simulate consecutive requests/flushes on the same service instances without `reset()` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ (resolved) | `PasswordEntityListener::$processedPasswords` is cleared at the start of every `onFlush`, keyed by entity + old hash, stores only `true`; other services only hold compile-time config |
| Static properties / `static` locals | ✅ | None in `src/`; `RouteNameMatcher::matches()` is a pure static function |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `PasswordEntityListener` implements `ResetInterface` and is tagged `kernel.reset` (safety net; it also clears itself per flush) |
| Request / user / locale captured in services | ✅ (resolved) | Nothing captured in constructors; `PasswordExpiryListener` only trusts the token storage when the main request matched a firewall (`_firewall_context`) |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used |
| Doctrine / EntityManager | ✅ (resolved) | `onFlush` listener no longer stores entities; the bundle never clears the application's EntityManager (identity-map clearing between requests remains the application's responsibility under scenario B) |
| Output, headers, `exit`, shutdown functions | ✅ | None; redirects and flashes go through `RequestEvent` and the session |
| Resources (files, sockets, cURL) held open | ✅ | None |
| Memory growth across requests | ✅ (resolved) | `$processedPasswords` only lives for one flush |
| Blocking I/O and timeouts | ⚠️ Low | Reset-route pattern resolution sorts all route names on each redirect; optional cache pool calls |
| Third-party static state | ✅ (resolved) | `Carbon::setLocale()` removed; locale set on a local Carbon instance |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` declares a `worker` block (line 15); `Caddyfile.dev` runs classic mode.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `nowo_password_policy.entity_listener.<entity>` (`EventListener\PasswordEntityListener`, `doctrine.event_listener` `onFlush`, `kernel.reset`) | yes, one per configured entity | `array<string, true> $processedPasswords`, cleared at the start of every flush | ✅ | ✅ |
| `EventListener\PasswordExpiryListener` (`kernel.request`) | yes | none written after construction (public constructor properties are not reassigned); per-request guard stored in request attributes; skips requests without `_firewall_context` | ✅ | ✅ |
| `Service\PasswordExpiryService` (alias `PasswordExpiryServiceInterface`) | yes | `?array $entities` filled by `addEntity()` DI method calls at container build, then read-only; `$cacheEnabled` set in constructor | ✅ | ✅ (reads the token at call time; callers outside the listener inherit W-04 residual) |
| `Service\PasswordPolicyService` | yes | none (`readonly` closure; hasher reference) | ✅ | ✅ |
| `Service\PasswordHistoryService` | yes | none | ✅ | ✅ |
| `Service\PasswordPolicyConfigurationService` | yes | `array $entityConfigurations` filled by DI method calls only | ✅ | ✅ |
| `nowo_password_policy.expiry_flash_throttle_storage.session` (`SessionExpiryFlashThrottleStorage`) | yes | none; reads the session from `RequestStack` per call | ✅ | ✅ |
| `nowo_password_policy.expiry_flash_throttle_storage.cache` (`CacheExpiryFlashThrottleStorage`) | yes | none; shared cache pool, keys hashed per subject | ✅ | ✅ |
| `Validator\PasswordPolicyValidator` | yes | none; locale applied to a local Carbon instance | ✅ | ✅ |

The auto-registered `ExpiryFlash\*` classes under the `Service\` resource are also stateless. `PasswordExpiryConfiguration` is an immutable value object built by the container.

## Findings

### W-01 — `$processedPasswords` survives across requests and skips later password history entries (High)

- **Where:** `src/EventListener/PasswordEntityListener.php:42` (property), `:127-129` (early `return null` when the old hash is already a key), `:156` (entry added). Nothing ever removes entries, and the class does not implement `ResetInterface`. The listener is a shared `doctrine.event_listener` service (`src/DependencyInjection/PasswordPolicyExtension.php:296-301`), so the same instance handles every flush of the worker.
- **Worker impact:** the guard was meant to avoid duplicate history rows inside one flush. In PHP-FPM the array dies at the end of the request; in a worker it lives until the worker restarts, **in both scenario A and B**, because `services_resetter` only resets `kernel.reset` services and DoctrineBundle does not recreate event listeners. Two realistic cases then make `createPasswordHistory()` return early. In that case no history row is written, `setPasswordChangedAt()` is not called, and the expiry cache is not invalidated:
  1. **Retry after a failed flush.** `onFlush` records the old hash, then the transaction fails (constraint violation on another column, deadlock, lost connection) and is rolled back. When the user retries in a later request served by the same worker, the stored hash is unchanged, so the retry is skipped: the old password is missing from history (it can be reused later) and `passwordChangedAt` keeps the old date (the user can be flagged as expired right after changing the password, or the expiry window is not restarted).
  2. **Same stored hash across users.** If several users share the same stored value before the change (a placeholder hash for SSO/invited users, unsalted legacy hashes, or the same plain value in fixtures), the first user's change blocks the history and timestamp update for every other user with that value in the same worker. This is a cross-user effect on a security control.
- **Recommendation:** clear the guard for each flush: reset `$this->processedPasswords = []` at the start of `onFlush()` (or key it by `spl_object_id($entity)` and clear it in `postFlush`/`onClear`), and implement `ResetInterface` as a safety net. Until this is fixed, do not run the bundle in FrankenPHP worker mode, or restart workers after every request that changes a password (not practical).
- **Status:** Resolved — `src/EventListener/PasswordEntityListener.php`: `onFlush()` calls `reset()` first; the guard key is `spl_object_id($entity)` + old hash, so two users with the same stored hash in one flush are both processed; the class implements `ResetInterface` and the definition is tagged `kernel.reset` (`src/DependencyInjection/PasswordPolicyExtension.php`). Tests: `PasswordEntityListenerTest::testRetryOfSamePasswordChangeInNextRequestCreatesHistoryAgain`, `testSameOldHashForDifferentUsersAcrossRequestsIsNotSkipped`, `testSameOldHashForDifferentUsersInOneFlushIsNotSkipped`, `testResetClearsProcessedPasswords`; `PasswordPolicyExtensionTest::testLoadWithMinimalConfig` checks the tag.

### W-02 — Password history entities and their users are retained forever (Medium)

- **Where:** `src/EventListener/PasswordEntityListener.php:156` stores the `PasswordHistoryInterface` object itself as the array value; that object references the user entity (`:149` via the `set<MappedBy>()` setter), which in turn references its history collection.
- **Worker impact:** every password change handled by the worker keeps a full object graph alive after the EntityManager has been cleared or reset (the objects become detached but are never garbage-collected). Memory grows without limit in both scenarios, and old user data (hashes, emails) stays in the worker's memory.
- **Recommendation:** fixed together with W-01; store only a `true` flag (or nothing) and clear it per flush. Meanwhile, cap worker lifetime with `max_requests` in the FrankenPHP `worker` block.
- **Status:** Resolved — the guard stores `true` instead of the history entity and is cleared per flush (same change as W-01).

### W-03 — `Carbon::setLocale()` changes a process-wide default on every validation (Medium)

- **Where:** `src/Validator/PasswordPolicyValidator.php:83` calls `Carbon::setLocale($this->translator->getLocale())` each time the constraint is validated; the locale is later used by `diffForHumans()` (`:157`).
- **Worker impact:** Carbon keeps its default locale in static state. In a worker it is never restored, so after one validation in (say) `es`, every later request in that worker that relies on Carbon's default locale (in the host app or other bundles) renders in `es` until another validation changes it. This does not leak user data, but it is cross-request and cross-user state, in both scenario A and B.
- **Recommendation:** use a local instance instead of the global: `Carbon::instance($createdAt)->locale($this->translator->getLocale())->diffForHumans()`, or save and restore the previous locale around the call.
- **Status:** Resolved — `src/Validator/PasswordPolicyValidator.php`: `Carbon::setLocale()` removed; `{{ days }}` is built from a local `Carbon::instance($createdAt)` with `locale($translator->getLocale())`. Test: `PasswordPolicyValidatorTest::testLocaleIsAppliedPerValidationWithoutChangingCarbonGlobalLocale` (two validations in `es` then `en` on the same instance; Carbon's global locale stays `en`).

### W-04 — Expiry decisions read `security.token_storage` at call time (Medium)

- **Where:** `src/Service/PasswordExpiryService.php:290-304` (`getCurrentUser()`), used by `isPasswordExpired()` (`:80-128`) and `prepareEntityClass()` (`:315-322`); `src/EventListener/PasswordExpiryListener.php:130-131`.
- **Worker impact:** nothing is cached in the services, which is correct. Under **A**, Symfony resets the token storage between requests. Under **B**, it is not reset: on a request that does not pass through a firewall which replaces the token (routes outside any firewall, `security: false` patterns, some stateless firewalls), the previous request's user is still in the token storage. The listener could then flash or redirect an anonymous visitor based on another user's expiry state, dispatch `PasswordExpiredEvent` for the wrong user, and log that user's identifier.
- **Recommendation:** keep `services_resetter` enabled. Hosts on scenario B must make sure every route with a `notified_routes` match is behind a firewall.
- **Status:** Resolved (bundle side) — `src/EventListener/PasswordExpiryListener.php` returns early when the main request has no `_firewall_context` attribute (set by SecurityBundle's firewall map, priority 8, before this listener's default priority 0), so a token left over from a previous request is never used on routes outside every firewall. Test: `PasswordExpiryListenerTest::testStaleTokenIsIgnoredOnRequestWithoutFirewallWithoutReset`. Residual framework responsibility: a firewall declared with `security: false` still sets `_firewall_context` without replacing the token, and `PasswordExpiryServiceInterface::isPasswordExpired()` called directly by application code reads whatever token is stored; under scenario B keep notified routes behind real firewalls (or keep `services_resetter` so the token storage is reset).

### W-05 — Reset route pattern resolution scans all routes on each redirect (Low)

- **Where:** `src/Service/PasswordExpiryService.php:243-258` (`getRouteCollection()->all()`, `sort()`, regex/glob per route) when `reset_password_route_pattern` is set and `redirect_on_expiry` is true.
- **Worker impact:** no state leak (the router keeps its own collection); only CPU per redirect on large route sets.
- **Recommendation:** prefer `reset_password_route_name` without a pattern on large applications.
- **Status:** Accepted — CPU cost only, no cross-request state; memoizing the result would add per-worker state for little gain.

No other findings. Good patterns: the per-request duplicate-flash guard lives in request attributes (`src/EventListener/PasswordExpiryListener.php:113`, `:217-226`); the expiry cache key includes the user class, id and `passwordChangedAt` timestamp (`src/Service/PasswordExpiryService.php:153-168`), so cached results cannot be served to another user; `CacheExpiryFlashThrottleStorage` is the documented choice for multi-worker deployments.

## Usage recommendations in worker mode

- W-01 to W-04 are fixed; the bundle is usable in worker mode without `services_resetter`. Keeping `services_resetter` enabled is still recommended (token storage and Doctrine identity map are framework/application owned). Use `expiry_listener.flash_throttle_storage: cache` backed by Redis/Memcached so throttling is consistent across workers.
- If you format dates with Carbon elsewhere, set the locale explicitly on each instance instead of relying on Carbon's global default (W-03).
- Custom `flash_throttle_storage_service`, `PasswordHistoryServiceInterface` or `PasswordExpiryServiceInterface` implementations must stay stateless, or implement `ResetInterface`.

## Re-audit triggers

Re-run this audit when a change adds or modifies: properties of `PasswordEntityListener` or `PasswordExpiryService`, any in-memory cache of expiry state, new Doctrine listeners (`postFlush`, `onClear`), new calls to global setters in third-party libraries (Carbon, Intl), or reads of the user outside `TokenStorage`.
