<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle;

/*
 * @deprecated since REQ-I18N-003; use {@see NowoPasswordPolicyBundle} instead.
 *             Kept for backward compatibility with existing `config/bundles.php` registrations.
 */
class_alias(NowoPasswordPolicyBundle::class, __NAMESPACE__ . '\PasswordPolicyBundle');
