<?php

declare(strict_types=1);

namespace OpenDemat\Core\Notification;

use Doctrine\DBAL\Connection;
use OpenDemat\Core\Entity\User;

/**
 * Lit les préférences de notification sans charger les profils Doctrine.
 * L'absence de sous-profil signifie que les notifications sont actives.
 */
class BundleNotificationPreference
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isMutedForUser(User $user, string $bundleKey): bool
    {
        if ($user->getId() === null) {
            return false;
        }

        return $this->isTruthyDatabaseValue($this->connection->fetchOne(
            'SELECT notifications_muted FROM user_bundle_profile WHERE user_id = :user_id AND bundle_key = :bundle_key',
            ['user_id' => $user->getId(), 'bundle_key' => $this->normalizeKey($bundleKey)]
        ));
    }

    public function isMutedForEmail(string $email, string $bundleKey): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }

        return $this->isTruthyDatabaseValue($this->connection->fetchOne(
            <<<'SQL'
                SELECT EXISTS (
                    SELECT 1
                    FROM user_bundle_profile ubp
                    INNER JOIN "user" u ON u.id = ubp.user_id
                    WHERE LOWER(u.email) = LOWER(:email)
                      AND ubp.bundle_key = :bundle_key
                      AND ubp.notifications_muted = TRUE
                )
                SQL,
            ['email' => $email, 'bundle_key' => $this->normalizeKey($bundleKey)]
        ));
    }

    /**
     * @return string[]
     */
    public function getUnmutedEmailsForRole(string $role, string $bundleKey): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT DISTINCT u.email
                FROM "user" u
                WHERE u.roles @> :role
                  AND u.email IS NOT NULL
                  AND u.email <> ''
                  AND NOT EXISTS (
                      SELECT 1
                      FROM user_bundle_profile ubp
                      WHERE ubp.user_id = u.id
                        AND ubp.bundle_key = :bundle_key
                        AND ubp.notifications_muted = TRUE
                  )
                SQL,
            [
                'role' => json_encode([$role], JSON_THROW_ON_ERROR),
                'bundle_key' => $this->normalizeKey($bundleKey),
            ]
        );
    }

    private function normalizeKey(string $bundleKey): string
    {
        $bundleKey = strtoupper(trim($bundleKey));
        if ($bundleKey === '') {
            throw new \InvalidArgumentException('La clé du bundle ne peut pas être vide.');
        }

        return $bundleKey;
    }

    private function isTruthyDatabaseValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
