# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/password-policy-bundle`  
**Last audited**: 2026-07-22

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and demo trees are out of Packagist scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoPasswordPolicyBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `PasswordPolicyBundle.php` | Deprecated class_alias BC shim | FR-BUNDLE-001 |
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

All locale files expose validator violation messages and expiry flash copy under domain `NowoPasswordPolicyBundle`.

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPasswordPolicyBundle.ar.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.bg.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.ca.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.cs.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.da.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.de.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.el.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.en.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.es.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.et.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.fi.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.fr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.he.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.hi.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.hr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.hu.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.id.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.it.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.ja.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.ko.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.lt.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.nl.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.no.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.pl.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.pt.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.pt_BR.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.ro.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.ru.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.sk.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.sl.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.sv.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.th.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.tr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.uk.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.vi.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.zh_CN.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoPasswordPolicyBundle.zh_TW.yaml` | i18n | FR-I18N-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 31 | 31 |
| Symfony config | 1 | 1 |
| Translations | 37 | 37 |
| **Total production sources** | **69** | **69** |
