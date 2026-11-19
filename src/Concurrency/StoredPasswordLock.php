<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Concurrency;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Utility\PersisterHelper;
use Nowo\PasswordPolicyBundle\Exceptions\PasswordChangeConflictException;

use function array_values;
use function sprintf;

/**
 * Locks a subject's row inside the host's flush and checks that its stored password is still the one the request
 * loaded (REQ-RUNTIME-003 rules 1 and 4: compare-and-set of the password change).
 *
 * Two requests that change one user's password both validate the new password against the history they loaded. Without
 * this check the later commit silently overwrites the earlier one, and the earlier new password is never written to
 * the history, so it can be reused at once (and both requests record the same old hash, pushing an older password out
 * of the window one change early). With it, the later flush meets the first one's committed password and fails with
 * {@see PasswordChangeConflictException}; nothing of it is written.
 *
 * The row lock is `FOR NO KEY UPDATE` on PostgreSQL (it does not conflict with the key-share lock the history row's
 * foreign key takes, so it adds no deadlock) and DBAL's `FOR UPDATE` elsewhere (MySQL, MariaDB); a platform without
 * one (SQLite, which locks the whole database for the write) runs the same comparison without a row lock. It runs on
 * the host's own connection, inside the flush transaction the host's ORM opened: the bundle opens, commits and wraps
 * nothing. Stateless: safe in FrankenPHP worker mode.
 */
final readonly class StoredPasswordLock
{
    /**
     * @param mixed $expected The password value the request loaded (the change set's old value)
     *
     * @throws PasswordChangeConflictException When the stored value differs, or the row is gone
     */
    public function assertUnchanged(EntityManagerInterface $entityManager, object $entity, string $passwordField, mixed $expected): void
    {
        $metadata      = $entityManager->getClassMetadata($entity::class);
        $connection    = $entityManager->getConnection();
        $platform      = $connection->getDatabasePlatform();
        $quoteStrategy = $entityManager->getConfiguration()->getQuoteStrategy();

        $queryBuilder = $connection->createQueryBuilder()
            ->select($quoteStrategy->getColumnName($passwordField, $metadata, $platform))
            ->from($quoteStrategy->getTableName($metadata, $platform));

        // Identifier columns (join columns too) in the order of the identifier values; types as the ORM binds them.
        $columns       = $metadata->getIdentifierColumnNames();
        $quotedColumns = $quoteStrategy->getIdentifierColumnNames($metadata, $platform);
        foreach (array_values($entityManager->getUnitOfWork()->getEntityIdentifier($entity)) as $index => $value) {
            $type = PersisterHelper::getTypeOfColumn($columns[$index], $metadata, $entityManager);
            $queryBuilder->andWhere(sprintf('%s = %s', $quotedColumns[$index], $queryBuilder->createNamedParameter($value, $type)));
        }

        $sql = $queryBuilder->getSQL();
        if ($platform instanceof PostgreSQLPlatform) {
            $sql .= ' FOR NO KEY UPDATE';
        } else {
            try {
                $sql = $queryBuilder->forUpdate()->getSQL();
            } catch (DBALException) {
                // No row lock on this platform (SQLite: the flush already holds the database write lock).
            }
        }

        $rows = $connection->fetchFirstColumn($sql, $queryBuilder->getParameters(), $queryBuilder->getParameterTypes());
        if ($rows === []) {
            throw new PasswordChangeConflictException($entity::class);
        }

        $stored = Type::getType($metadata->getTypeOfField($passwordField) ?? 'string')->convertToPHPValue($rows[0], $platform);
        if ($stored !== $expected) {
            throw new PasswordChangeConflictException($entity::class);
        }
    }
}
