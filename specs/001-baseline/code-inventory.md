# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/password-policy-bundle`  
**Last audited**: 2026-07-15

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and demo trees are out of Packagist scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `PasswordPolicyBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/PasswordPolicyExtension.php` | DI extension, listeners, throttle storage | FR-CFG-002, FR-LIST-002, FR-FLASH-001 |
| `Model/HasPasswordPolicyInterface.php` | Entity contract | FR-MODEL-001 |
| `Model/PasswordHistoryInterface.php` | History entity contract | FR-MODEL-002 |
| `Model/PasswordExpiryConfiguration.php` | Per-entity expiry DTO | FR-MODEL-003 |
| `Model/ExpiryFlashStrategy.php` | Flash strategy enum values | FR-MODEL-004 |
| `Model/ExpiryFlashThrottleStorageType.php` | Throttle storage backend values | FR-MODEL-005 |
| `Service/PasswordPolicyService.php` | Reuse & extension checks | FR-POL-001 |
| `Service/PasswordPolicyServiceInterface.php` | Policy service contract | FR-POL-001 |
| `Service/PasswordHistoryService.php` | History cleanup | FR-HIST-001 |
| `Service/PasswordHistoryServiceInterface.php` | History service contract | FR-HIST-001 |
| `Service/PasswordExpiryService.php` | Expiry detection & cache | FR-EXP-001 |
| `Service/PasswordExpiryServiceInterface.php` | Expiry service contract | FR-EXP-001 |
| `Service/PasswordPolicyConfigurationService.php` | Per-entity config lookup | FR-CFG-003 |
| `Service/ExpiryFlash/ExpiryFlashThrottleStorageInterface.php` | Flash throttle contract | FR-FLASH-001 |
| `Service/ExpiryFlash/SessionExpiryFlashThrottleStorage.php` | Session-backed throttle | FR-FLASH-002 |
| `Service/ExpiryFlash/CacheExpiryFlashThrottleStorage.php` | Cache-backed throttle | FR-FLASH-003 |
| `Validator/PasswordPolicy.php` | Constraint definition | FR-VAL-001 |
| `Validator/PasswordPolicyValidator.php` | Reuse validation | FR-VAL-002 |
| `EventListener/PasswordEntityListener.php` | Doctrine onFlush history | FR-LIST-001 |
| `EventListener/PasswordExpiryListener.php` | Request expiry enforcement | FR-LIST-002 |
| `Event/PasswordChangedEvent.php` | Domain event | FR-EVT-001 |
| `Event/PasswordExpiredEvent.php` | Domain event | FR-EVT-002 |
| `Event/PasswordHistoryCreatedEvent.php` | Domain event | FR-EVT-003 |
| `Event/PasswordReuseAttemptedEvent.php` | Domain event | FR-EVT-004 |
| `Traits/PasswordHistoryTrait.php` | History entity helper | FR-TRAIT-001 |
| `Util/RouteNameMatcher.php` | Route pattern matching | FR-ROUTE-001 |
| `Exceptions/ConfigurationException.php` | Config errors | FR-ERR-001 |
| `Exceptions/RuntimeException.php` | Runtime errors | FR-ERR-002 |
| `Exceptions/ValidationException.php` | Validator errors | FR-ERR-003 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yml` | Service wiring | FR-DI-001 |

## Translations (`src/Resources/translations/`)

All locale files expose validator violation messages and expiry flash copy under domain `PasswordPolicyBundle`.

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/PasswordPolicyBundle.ar.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.bg.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.ca.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.cs.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.da.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.de.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.el.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.en.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.es.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.et.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.fi.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.fr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.he.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.hi.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.hr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.hu.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.id.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.it.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.ja.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.ko.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.lt.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.nl.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.no.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.pl.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.pt.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.pt_BR.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.ro.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.ru.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.sk.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.sl.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.sv.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.th.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.tr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.uk.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.vi.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.zh_CN.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/PasswordPolicyBundle.zh_TW.yaml` | i18n | FR-I18N-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 31 | 31 |
| Symfony config | 1 | 1 |
| Translations | 37 | 37 |
| **Total production sources** | **69** | **69** |
