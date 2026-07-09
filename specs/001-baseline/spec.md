# Feature Specification: PasswordPolicyBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/password-policy-bundle`  
**Configuration root**: `nowo_password_policy`

Symfony bundle enforcing password history (reuse prevention), optional extension detection, password expiry on configured routes, Doctrine-backed history persistence, and domain events for auditing.

---

## User Scenarios & Testing

### User Story 1 — Reject password reuse (Priority: P1)

As a security officer, I configure password history on user entities so that registration and password-change forms reject passwords that match recent hashes.

**Independent Test**: Apply `PasswordPolicy` constraint to a `HasPasswordPolicyInterface` entity with seeded history; submit a previously used password → violation with `PASSWORD_IN_HISTORY` code.

**Acceptance Scenarios**:

1. **Given** history contains a matching hash, **When** validator runs, **Then** violation uses `{{ days }}` parameter from history `createdAt`.
2. **Given** `detect_password_extensions=true`, **When** new password extends an old one (suffix/prefix heuristics), **Then** violation uses `PASSWORD_EXTENSION` code and optional `extensionMessage`.
3. **Given** entity does not implement `HasPasswordPolicyInterface`, **When** validator runs, **Then** `ValidationException` is thrown.

---

### User Story 2 — Auto-track password history (Priority: P1)

As an integrator, I rely on Doctrine `onFlush` so password changes automatically create history rows and trim entries beyond `passwords_to_remember`.

**Independent Test**: Flush entity with changed password field → `PasswordHistoryCreatedEvent` dispatched, oldest entries removed when limit exceeded.

**Acceptance Scenarios**:

1. **Given** password field change detected in unit of work, **When** `PasswordEntityListener::onFlush` runs, **Then** prior hash is persisted to history collection and `passwordChangedAt` updated.
2. **Given** history count exceeds limit, **When** cleanup runs, **Then** `PasswordHistoryService` removes oldest entries newest-first.
3. **Given** expiry cache enabled, **When** password changes, **Then** `PasswordExpiryService` cache is invalidated for the user.

---

### User Story 3 — Enforce password expiry on routes (Priority: P1)

As an integrator, I define notified routes and optional redirect so expired passwords surface flash messages or redirect to reset password.

**Independent Test**: Authenticated user with expired password hits a notified route → flash message; with `redirect_on_expiry=true` → redirect to resolved reset route.

**Acceptance Scenarios**:

1. **Given** route matches `notified_routes` and not `excluded_notified_routes`, **When** password is expired, **Then** `PasswordExpiredEvent` fires and flash is added once per request.
2. **Given** `reset_password_route_pattern` set, **When** resolving reset URL, **Then** `RouteNameMatcher` picks first alphabetical matching route or falls back to `reset_password_route_name`.
3. **Given** `redirect_on_expiry=true`, **When** expiry detected on main request, **Then** `RedirectResponse` to generated reset URL.

---

### User Story 4 — Configure per-entity policies (Priority: P2)

As an integrator, I map multiple entity classes under `entities` with distinct field names, history limits, expiry days, and extension settings.

**Acceptance Scenarios**:

1. **Given** valid entity FQCN in config, **When** extension loads, **Then** dedicated `PasswordEntityListener` and `PasswordExpiryConfiguration` are registered per entity.
2. **Given** duplicate reset routes across entities, **When** extension loads, **Then** `ConfigurationException` prevents ambiguous routing.
3. **Given** `enable_cache=true`, **When** expiry checked repeatedly, **Then** result cached with configurable TTL until password change.

---

### Edge Cases

- Null/empty password in validator: skipped (no violation).
- Password verification tries `password_verify()` first, then Symfony hasher via cloned user or temporary `setPassword` fallback — never plain hash comparison.
- Sub-requests: expiry listener processes only main requests.
- FrankenPHP / duplicate kernel events: request attribute prevents duplicate flash messages.
- Missing entity class at compile time: configuration exception during container build.

---

## Requirements

### Bundle & DI

- **FR-BUNDLE-001**: `PasswordPolicyBundle` MUST expose `PasswordPolicyExtension` as container extension.
- **FR-DI-001**: `services.yml` MUST autowire services under `Service\`, wire `PasswordPolicyValidator`, and register `PasswordExpiryListener` manually (autowire disabled) with explicit arguments from extension.
- **FR-CFG-001**: `Configuration` MUST define `nowo_password_policy` tree: required `entities` map, `expiry_listener`, `log_level`, `enable_logging`, `enable_cache`, `cache_ttl`.
- **FR-CFG-002**: `PasswordPolicyExtension` MUST load services, register per-entity listeners, configure validator logging/events, inject cache into `PasswordExpiryService`, and validate entity classes exist.
- **FR-CFG-003**: `PasswordPolicyConfigurationService` MUST resolve per-entity settings (extension detection, field names) for validator and listeners.

### Domain model

- **FR-MODEL-001**: `HasPasswordPolicyInterface` MUST define password, history collection, change timestamp, and identity accessors consumed by services and listeners.
- **FR-MODEL-002**: `PasswordHistoryInterface` MUST expose hashed password and `createdAt` for reuse messaging.
- **FR-MODEL-003**: `PasswordExpiryConfiguration` MUST carry per-entity expiry days, notified/excluded routes, and reset route resolution settings.
- **FR-TRAIT-001**: `PasswordHistoryTrait` MUST provide default history collection helpers for Doctrine entities.

### Password history

- **FR-HIST-001**: `PasswordHistoryService` MUST sort history by `createdAt` descending and remove entries beyond configured limit via entity `removePasswordHistory()`.
- **FR-LIST-001**: `PasswordEntityListener` on `onFlush` MUST detect password field changes, create history entries, dispatch `PasswordChangedEvent` / `PasswordHistoryCreatedEvent`, deduplicate via processed map, and trigger cleanup.

### Password reuse policy

- **FR-POL-001**: `PasswordPolicyService` MUST locate history by plain password (`getHistoryByPassword`) and by extension heuristics (`getHistoryByPasswordExtension`) using secure verification only.
- **FR-VAL-001**: `PasswordPolicy` constraint MUST expose `message`, `extensionMessage`, `detectExtensions`, `extensionMinLength` options.
- **FR-VAL-002**: `PasswordPolicyValidator` MUST dispatch `PasswordReuseAttemptedEvent`, log attempts when enabled, and build violations with translation parameters.

### Password expiry

- **FR-EXP-001**: `PasswordExpiryService` MUST determine expiry from `passwordChangedAt` + `expiry_days`, optionally cache per user, invalidate on password change, and resolve reset route names.
- **FR-LIST-002**: `PasswordExpiryListener` on `kernel.request` MUST match routes via `RouteNameMatcher`, add configurable flash messages, optionally redirect, and dispatch `PasswordExpiredEvent`.
- **FR-ROUTE-001**: `RouteNameMatcher` MUST support literal names, globs (`*`, `?`), and delimited PCRE patterns.

### Events & errors

- **FR-EVT-001**: `PasswordChangedEvent` carries entity after password change.
- **FR-EVT-002**: `PasswordExpiredEvent` carries entity when expiry enforced.
- **FR-EVT-003**: `PasswordHistoryCreatedEvent` carries new history entry.
- **FR-EVT-004**: `PasswordReuseAttemptedEvent` carries entity and matching history.
- **FR-ERR-001**: `ConfigurationException` for invalid bundle config (missing entity class, duplicate routes).
- **FR-ERR-002**: `RuntimeException` for listener/runtime failures.
- **FR-ERR-003**: `ValidationException` when constraint applied to wrong entity type.

### Internationalization

- **FR-I18N-001**: Translation files under `Resources/translations/PasswordPolicyBundle.*.yaml` MUST provide keys for validator messages and expiry flash title/message in all shipped locales.

---

## Key Entities

- **HasPasswordPolicyInterface**: Application user (or account) entity contract.
- **PasswordHistoryInterface**: Stored prior password hash with timestamp.
- **PasswordExpiryConfiguration**: Runtime DTO assembled from YAML per entity class.

---

## Success Criteria

- **SC-001**: **64/64** production files mapped in [`code-inventory.md`](code-inventory.md).
- **SC-002**: Configuration keys in `docs/CONFIGURATION.md` match `Configuration.php`.
- **SC-003**: PHPUnit + PHPStan pass (`composer qa`).
- **SC-004**: Reuse and expiry flows covered by tests under `tests/`.
- **SC-005**: No password hash compared via plain string equality in policy service.

---

## Configuration reference (normative defaults)

| Key | Default | Behavior |
| --- | --- | --- |
| `entities.*.password_field` | `password` | Monitored hash field |
| `entities.*.password_history_field` | `passwordHistory` | History collection |
| `entities.*.passwords_to_remember` | `3` | Max history entries |
| `entities.*.expiry_days` | `90` | Days until expiry |
| `entities.*.reset_password_route_name` | *(required)* | Fallback reset route |
| `entities.*.detect_password_extensions` | `false` | Extension heuristic |
| `entities.*.extension_min_length` | `4` | Min base length for extensions |
| `expiry_listener.redirect_on_expiry` | `false` | Flash-only vs redirect |
| `expiry_listener.priority` | `0` | Kernel listener priority |
| `enable_logging` | `true` | PSR-3 logging of policy events |
| `enable_cache` | `false` | Expiry check caching |
| `cache_ttl` | `3600` | Cache TTL seconds |

---

## Explicit non-goals

- Password complexity rules (use PasswordStrengthBundle).
- Account lockout or rate limiting.
- Storing or hashing passwords (application responsibility).
- Demo-only behavior unless documented as stable API.

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `composer qa` or `make release-check` |
| PHP tests | `vendor/bin/phpunit` |
| Static analysis | `vendor/bin/phpstan analyse` |
| Code inventory | Row count matches `find src -type f \| wc -l` |

When changing behavior, update this spec, `code-inventory.md`, tests, and integrator docs.
