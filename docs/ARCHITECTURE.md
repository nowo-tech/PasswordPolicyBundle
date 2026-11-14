# Architecture — PasswordPolicyBundle

PasswordPolicyBundle is an **infrastructure** bundle (REQ-ARCH-001): password history, reuse checks, expiry and GDPR
erasure for your users. By default it works on your Doctrine user entity; every part of it is also reachable through
ports with scalar or value-object signatures, so a DDD / hexagonal host keeps its domain free of the bundle and of
Doctrine. This page lists the public surface, the extension points and how both kinds of host wire them.

## Table of contents

- [Public surface](#public-surface)
- [Ports and extension points (SPI)](#ports-and-extension-points-spi)
- [Events and the unit of work](#events-and-the-unit-of-work)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)
- [Worker mode](#worker-mode)

## Public surface

| Kind | Types |
| --- | --- |
| Ports with scalar / VO signatures | `History\PasswordHistoryRepositoryInterface`, `History\PasswordHistoryRecorderInterface`, `Subject\PasswordReuseCheckerInterface`, `Subject\PasswordHashVerifierInterface`, `Subject\PasswordPolicySubjectResolverInterface`, `Service\PasswordExpiryCheckerInterface` (since 1.12.0), `PersonalData\PasswordPolicyPersonalDataEraserInterface`, `PersonalData\PasswordPolicyPersonalDataExporterInterface`, `PersonalData\PasswordPolicyPersonalDataSubjectResolverInterface` |
| SPI (you implement or alias it) | `Subject\CurrentPasswordPolicySubjectProviderInterface` (since 1.12.0), `Service\ExpiryFlash\ExpiryFlashThrottleStorageInterface`, the ports above |
| Entity-typed services (Doctrine adapter variant) | `Service\PasswordPolicyServiceInterface`, `Service\PasswordHistoryServiceInterface`, `Service\PasswordExpiryServiceInterface`, `Service\CurrentPasswordMatcherInterface` |
| Value objects | `Model\PasswordPolicySubject` (+ `PasswordPolicySubjectInterface`), `Model\PasswordHistoryEntry`, `Model\PasswordReuseMatch`, `PersonalData\PasswordPolicyPersonalDataSubject`, `PersonalData\PasswordPolicyErasureResult` |
| Entity contracts (standard hosts) | `Model\HasPasswordPolicyInterface`, `Model\PasswordHistoryInterface`; optional `Traits\PasswordHistoryTrait` |
| Events | see [EVENTS.md](EVENTS.md) |

No entity, mapped superclass or base class is shipped: the host maps its own user and history classes (attributes or
XML). The trait is a convenience; its fields are listed in [USAGE — Mapping the history entity](USAGE.md#mapping-the-history-entity).

## Ports and extension points (SPI)

| Port | Default | Replace it to… |
| --- | --- | --- |
| `History\PasswordHistoryRepositoryInterface` | `DoctrinePasswordHistoryRepository` (the user's history association; persists, never flushes) | keep the history in your aggregate, a DBAL table or an API |
| `History\PasswordHistoryRecorderInterface` | `PasswordHistoryRecorder` | record the previous hash from your own use case (`history_listener: false`) |
| `Subject\PasswordPolicySubjectResolverInterface` | `DefaultPasswordPolicySubjectResolver` | find the subject of `#[PasswordPolicy]` on a command / DTO |
| `Subject\PasswordReuseCheckerInterface`, `Subject\PasswordHashVerifierInterface` | `PasswordReuseChecker`, `DefaultPasswordHashVerifier` | check reuse / verify hashes from your use case |
| `Service\PasswordExpiryCheckerInterface` (since 1.12.0) | `PasswordExpiryService` | ask "has this subject's password expired?" without an entity or a token |
| `Subject\CurrentPasswordPolicySubjectProviderInterface` (since 1.12.0) | `NullCurrentPasswordPolicySubjectProvider` (only entity users are checked for expiry) | let the expiry listener check a non-entity security user: alias it to `TokenPasswordPolicySubjectProvider` or to your own provider |
| `Service\ExpiryFlash\ExpiryFlashThrottleStorageInterface` | session or cache storage (`expiry_listener.flash_throttle_storage`) | keep the throttle elsewhere (`flash_throttle_storage_service`) |
| `PersonalData\*Interface` | the bundle's eraser, exporter and subject resolver | GDPR erasure / export of your own adapters |

Alias the interface in `config/services.yaml` to replace a default; the bundle's own services are typed against the
interfaces. Configuration comes from the `nowo_password_policy` tree only (REQ-ARCH-001 rule 8).

## Events and the unit of work

The bundle never flushes your entity manager (rule 6): the Doctrine history listener adds the history row inside
your flush (`onFlush`), and `DoctrinePasswordHistoryRepository` persists without flushing.

Events are dispatched **after the commit** (rule 7). Since 1.12.0 the Doctrine listener dispatches nothing inside
`onFlush`: on `postFlush`, for each change and in order, it dispatches `PasswordHistoryCreatedEvent`,
`PasswordChangedEvent` and `PasswordChangeRecordedEvent`. A flush that fails dispatches none of them, and the pending
changes are dropped at the next flush or at the request / message / command boundary. Inside an outer transaction of
yours, `postFlush` runs before that transaction commits: dispatch your own follow-up work after your commit.

Every event carries ids: the scalar events (`PasswordChangeRecordedEvent`, `PasswordReuseDetectedEvent`,
`PasswordExpiryDetectedEvent`) carry only the subject class and id; the entity events also expose
`getSubjectClass()` / `getSubjectId()`, and their entity accessors are deprecated since 1.13.0 (removed in 2.0). Each
entity event has a scalar twin dispatched alongside, for entities as well as read-only subjects (since 1.13.0
`PasswordReuseDetectedEvent` follows `PasswordReuseAttemptedEvent`).

## Standard Symfony and DDD hosts

**Standard Symfony host.** Your user entity implements `HasPasswordPolicyInterface`, a history entity implements
`PasswordHistoryInterface` (attribute or XML mapping, optionally with `PasswordHistoryTrait`), and you list the user
class under `nowo_password_policy.entities`. Put `#[PasswordPolicy]` on the password property of the form model;
the history, `passwordChangedAt` and the expiry redirect work with no code. See [USAGE.md](USAGE.md).

**DDD / hexagonal host.**

- Map your classes with Doctrine **XML** (no attribute, no base class, no trait in the domain) — or keep the history
  out of Doctrine with `history_listener: false`, your own `PasswordHistoryRepositoryInterface` adapter and
  `PasswordHistoryRecorderInterface::record()` called from your use case. The test suite covers an XML-mapped host
  with the default listener (`tests/Integration/Doctrine/PasswordPolicyXmlHostDoctrineTest.php`).
- Validate commands with `#[PasswordPolicy]` and a `PasswordPolicySubjectAwareInterface` (or your own subject
  resolver); the subject is a `PasswordPolicySubject` built server side.
- **Expiry without an entity user** (since 1.12.0): make your security adapter implement
  `PasswordPolicySubjectInterface` (or `PasswordPolicySubjectAwareInterface`) and opt in:

```yaml
# config/services.yaml
services:
  Nowo\PasswordPolicyBundle\Subject\CurrentPasswordPolicySubjectProviderInterface:
    '@Nowo\PasswordPolicyBundle\Subject\TokenPasswordPolicySubjectProvider'
  # or your own provider, e.g. a lookup of the domain user by the token's user identifier
```

  The subject class must be a `nowo_password_policy.entities` key (or extend / implement one); its `expiry_days`,
  `notified_routes` and reset route apply. The listener then flashes / redirects as for an entity user and dispatches
  `PasswordExpiryDetectedEvent`. From your own code, call `PasswordExpiryCheckerInterface::isSubjectPasswordExpired()`
  and `invalidateSubjectCache()` after you change a password.
- Translate the bundle events into your own events in an infrastructure listener; do not pass them into the domain.

## Worker mode

Per-flush state is cleared at every flush and at the request / message / command boundary; nothing is kept across
units of work. Safe in a FrankenPHP worker with the kernel not reset; see
[FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
