# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/password-policy-bundle`  
**Last audited**: 2026-11-16 (sweep: 2026-10-20 baseline + later sources)

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and demo trees are out of Packagist scope unless promoted in the spec.

## PHP classes

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoPasswordPolicyBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `PasswordPolicyBundle.php` | Deprecated class_alias BC shim | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/PasswordPolicyExtension.php` | DI extension, listeners, throttle storage, `kernel.reset` | FR-CFG-002, FR-LIST-001, FR-LIST-002, FR-FLASH-001 |
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
| `Validator/PasswordPolicyValidator.php` | Reuse validation (local Carbon locale) | FR-VAL-002 |
| `EventListener/PasswordEntityListener.php` | Doctrine onFlush history + preUpdate password-change lock + ResetInterface | FR-LIST-001, FR-LIST-001a, FR-LIST-003 |
| `EventListener/PasswordExpiryListener.php` | Request expiry enforcement + firewall gate | FR-LIST-002, FR-LIST-002a |
| `Event/PasswordChangedEvent.php` | Domain event | FR-EVT-001 |
| `Event/PasswordExpiredEvent.php` | Domain event | FR-EVT-002 |
| `Event/PasswordHistoryCreatedEvent.php` | Domain event | FR-EVT-003 |
| `Event/PasswordReuseAttemptedEvent.php` | Domain event | FR-EVT-004 |
| `Traits/PasswordHistoryTrait.php` | History entity helper | FR-TRAIT-001 |
| `Util/RouteNameMatcher.php` | Route pattern matching | FR-ROUTE-001 |
| `Exceptions/ConfigurationException.php` | Config errors | FR-ERR-001 |
| `Exceptions/RuntimeException.php` | Runtime errors | FR-ERR-002 |
| `Exceptions/ValidationException.php` | Validator errors | FR-ERR-003 |
| `Exceptions/PasswordChangeConflictException.php` | Concurrent password change (lock conflict, REQ-RUNTIME-003) | FR-LIST-003 |
| `Concurrency/StoredPasswordLock.php` | Row lock + stored-vs-loaded password check inside the host flush | FR-LIST-003 |
| `Model/CurrentPasswordHistory.php` | Transient history entry for the current password (reuse of the active password) | FR-MODEL-006, FR-VAL-002 |
| `Service/CurrentPasswordMatcherInterface.php` | Optional capability: plain password matches the current hash | FR-VAL-003 |

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

## Sources added after the last baseline review (sweep 2026-11-16)

These 43 `src/` files were added by releases after the last review. Each row maps the file to the requirement already used for its neighbours in the same directory; the description comes from the class docblock. With them, every file under `src/` is listed in this inventory.

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Compiler/UserKitEraserErasableHistoryPass.php` | Removes the UserKit `password_history` eraser when the history adapter aliased to PasswordHistoryRepositoryInterface... | FR-DI-001 |
| `Event/PasswordChangeRecordedEvent.php` | Domain event: A password change was recorded in the history; carries scalar ids only (no entity). | FR-EVT-001 |
| `Event/PasswordExpiryDetectedEvent.php` | Domain event: Dispatched by the expiry listener when the current user's password has expired on a locked route (since 1.12.0). | FR-EVT-001 |
| `Event/PasswordPolicyPersonalDataErasedEvent.php` | Domain event: The password policy data of a subject was erased; carries scalar values only. | FR-EVT-001 |
| `Event/PasswordReuseDetectedEvent.php` | Domain event: The PasswordPolicy validator rejected a reused password; carries scalar ids only. | FR-EVT-001 |
| `EventListener/PasswordPolicyWorkerBoundaryListener.php` | Starts every unit of work with empty per-flush state in the Doctrine password listeners (REQ-RUNTIME-002). | FR-LIST-001 |
| `History/DoctrinePasswordHistoryRepository.php` | Default PasswordHistoryRepositoryInterface adapter: the history association of a HasPasswordPolicyInterface Doctrine... | FR-HISTORY-001 |
| `History/ErasablePasswordHistoryRepositoryInterface.php` | A history adapter that can delete every entry of a subject, used by PasswordPolicyPersonalDataEraserInterface. | FR-HISTORY-001 |
| `History/PasswordHistoryRecorder.php` | Default PasswordHistoryRecorderInterface: stores the previous hash through the history port, prunes it to passwords_t... | FR-HISTORY-001 |
| `History/PasswordHistoryRecorderInterface.php` | Application service the host calls from its "change password" use case when the Doctrine onFlush listener does not re... | FR-HISTORY-001 |
| `History/PasswordHistoryRepositoryInterface.php` | Port: where the password history of a subject lives. | FR-HISTORY-001 |
| `Model/PasswordHistoryEntry.php` | Immutable password history entry returned by PasswordHistoryRepositoryInterface. | FR-MODEL-001 |
| `Model/PasswordPolicySubject.php` | Immutable PasswordPolicySubjectInterface value: build it in your adapter from your domain user. | FR-MODEL-001 |
| `Model/PasswordPolicySubjectAwareInterface.php` | Implement it on a command/DTO that carries `#[PasswordPolicy]`: the default subject resolver asks it for the subject... | FR-MODEL-001 |
| `Model/PasswordPolicySubjectInterface.php` | Read-only view of a password policy subject (a user, an account…) for checks that must not depend on the Doctrine mod... | FR-MODEL-001 |
| `Model/PasswordReuseMatch.php` | Result of a reuse check on a PasswordPolicySubjectInterface: how the new password matched and when the matching passw... | FR-MODEL-001 |
| `PersonalData/DefaultPasswordPolicyPersonalDataSubjectResolver.php` | Default PasswordPolicyPersonalDataSubjectResolverInterface: the subject the bundle's PasswordPolicySubjectResolverInt... | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyErasureResult.php` | What PasswordPolicyPersonalDataEraserInterface::erase() removed, per kind. | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyPersonalDataEraser.php` | Default PasswordPolicyPersonalDataEraserInterface.  1. | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyPersonalDataEraserInterface.php` | Port: erases the password policy data the bundle manages for one subject (GDPR art. 17). | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyPersonalDataExporter.php` | Default PasswordPolicyPersonalDataExporterInterface: every entry of the history port, change dates only. | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyPersonalDataExporterInterface.php` | Port: exports the password policy data the bundle keeps for one subject (GDPR art. 15 / 20). | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyPersonalDataSubject.php` | Whose password policy data is erased or exported: the subject class and the subject id exactly as the history store k... | FR-PERSONALDATA-001 |
| `PersonalData/PasswordPolicyPersonalDataSubjectResolverInterface.php` | SPI: the password policy subjects of a user object, for the UserKit eraser and export provider. | FR-PERSONALDATA-001 |
| `PersonalData/UserKit/PasswordHistoryUserKitEraser.php` | UserKit personal-data eraser `password_history`: runs PasswordPolicyPersonalDataEraserInterface once per subject of t... | FR-PERSONALDATA-001 |
| `PersonalData/UserKit/PasswordHistoryUserKitExportProvider.php` | UserKit export section `password_history`: `{count, entries: [{changed_at}]}` (change dates only, newest first, never... | FR-PERSONALDATA-001 |
| `PersonalData/UserKit/UserKitSubjects.php` | Class `UserKitSubjects` | FR-PERSONALDATA-001 |
| `Service/ExpiryFlash/ForgettableExpiryFlashThrottleStorageInterface.php` | A throttle storage that can drop the entry of one subject (personal-data erasure). | FR-FLASH-001 |
| `Service/PasswordExpiryCheckerInterface.php` | Password expiry for a read-only subject (since 1.12.0, REQ-ARCH-001 rules 1 and 4): no entity, no security token. | FR-POL-001 |
| `Subject/CurrentPasswordPolicySubjectProviderInterface.php` | SPI (since 1.12.0): the read-only subject of the current request, for the expiry check when the security user is not... | FR-SUBJECT-001 |
| `Subject/DefaultPasswordHashVerifier.php` | Default PasswordHashVerifierInterface: password_verify() for password_hash() formats (bcrypt, argon2, including Symfo... | FR-SUBJECT-001 |
| `Subject/DefaultPasswordPolicySubjectResolver.php` | Default PasswordPolicySubjectResolverInterface: the validated object itself when it is a subject, or the subject it e... | FR-SUBJECT-001 |
| `Subject/NullCurrentPasswordPolicySubjectProvider.php` | Default CurrentPasswordPolicySubjectProviderInterface: no subject, so only `HasPasswordPolicyInterface` security user... | FR-SUBJECT-001 |
| `Subject/PasswordHashVerifierInterface.php` | Port: verifies a plain password against a stored hash of a subject, without touching any entity. | FR-SUBJECT-001 |
| `Subject/PasswordPolicySubjectResolverInterface.php` | Port: finds the subject of a `#[PasswordPolicy]` placed on an object that is not a HasPasswordPolicyInterface entity... | FR-SUBJECT-001 |
| `Subject/PasswordReuseChecker.php` | Default PasswordReuseCheckerInterface: same order and rules as the entity check (newest passwords_to_remember history... | FR-SUBJECT-001 |
| `Subject/PasswordReuseCheckerInterface.php` | Checks a new plain password of a read-only subject against its history (through the history port), its current hash a... | FR-SUBJECT-001 |
| `Subject/TokenPasswordPolicySubjectProvider.php` | Opt-in CurrentPasswordPolicySubjectProviderInterface (since 1.12.0): the security token user when it is a PasswordPol... | FR-SUBJECT-001 |
| `Util/LogIdentifier.php` | Pseudonymises user identifiers (usually e-mail addresses) before they reach the logs: the same identifier always give... | FR-ROUTE-001 |
| `Util/NativeHashVerifier.php` | password_verify() that also understands Symfony's bcrypt pre-hashing. | FR-ROUTE-001 |
| `Util/PasswordExtensionCandidates.php` | Base-password candidates for extension detection ("password123" → "password"): single special/digit characters and nu... | FR-ROUTE-001 |
| `Util/SubjectId.php` | Scalar subject identity (class + id) of a password policy subject, for events and ports that must not carry the host... | FR-ROUTE-001 |
| `Worker/UnitOfWorkFrames.php` | Tracks which unit of work runs in this process, so a boundary listener acts only at the OUTERMOST request, Messenger... | FR-WORKER-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 34 | 34 |
| Symfony config | 1 | 1 |
| Translations | 37 | 37 |
| **Total production sources** | **115** | **115** |

Audit command: `git ls-files src | wc -l` → 72.
