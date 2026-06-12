<?php

namespace OpenDemat\Core\Directory;

final readonly class LdapPersonnelDirectory
{
    public function __construct(
        private bool $enabled,
        private string $host,
        private int $port,
        private string $baseDn,
        private bool $useTls,
        private ?string $bindDn,
        private ?string $bindPassword,
        private int $timeout,
        private int $maxResults,
    ) {
    }

    /**
     * @return list<PersonnelEntry>
     */
    public function search(string $query, ?int $limit = null): array
    {
        $query = trim($query);
        if (!$this->enabled || mb_strlen($query) < 2) {
            return [];
        }

        if (!extension_loaded('ldap')) {
            throw new \RuntimeException('PHP LDAP extension is not enabled.');
        }

        $connection = @ldap_connect(sprintf('ldap://%s:%d', $this->host, $this->port));
        if ($connection === false) {
            return $this->searchWithLdapSearch($query, $limit);
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $this->timeout);

        if ($this->useTls && !@ldap_start_tls($connection)) {
            return $this->searchWithLdapSearch($query, $limit);
        }

        $bound = $this->bindDn
            ? @ldap_bind($connection, $this->bindDn, (string) $this->bindPassword)
            : @ldap_bind($connection);

        if (!$bound) {
            return $this->searchWithLdapSearch($query, $limit);
        }

        $limit = max(1, min($limit ?? $this->maxResults, $this->maxResults));
        $escaped = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
        $filter = sprintf(
            '(&(|(objectClass=person)(objectClass=inetOrgPerson)(objectClass=supannPerson))(|(cn=*%1$s*)(displayName=*%1$s*)(sn=*%1$s*)(givenName=*%1$s*)(uid=*%1$s*)(mail=*%1$s*)))',
            $escaped
        );

        $attributes = [
            'uid',
            'supannaliaslogin',
            'cn',
            'displayname',
            'sn',
            'givenname',
            'mail',
            'title',
            'employeetype',
            'ou',
            'departmentnumber',
            'description',
        ];

        $result = @ldap_search($connection, $this->baseDn, $filter, $attributes, 0, $limit);
        if ($result === false) {
            return $this->searchWithLdapSearch($query, $limit);
        }

        $entries = ldap_get_entries($connection, $result);
        if (!is_array($entries)) {
            return [];
        }

        $personnel = [];
        for ($i = 0, $count = (int) ($entries['count'] ?? 0); $i < $count; ++$i) {
            if (!isset($entries[$i]) || !is_array($entries[$i])) {
                continue;
            }

            $entry = $this->mapEntry($entries[$i]);
            if ($entry !== null) {
                $personnel[] = $entry;
            }
        }

        usort($personnel, static fn(PersonnelEntry $a, PersonnelEntry $b): int => strcasecmp($a->displayName, $b->displayName));

        return $personnel;
    }

    /**
     * @return list<PersonnelEntry>
     */
    private function searchWithLdapSearch(string $query, int $limit): array
    {
        $binary = '/usr/bin/ldapsearch';
        if (!is_executable($binary)) {
            throw new \RuntimeException('Unable to create LDAP connection and ldapsearch fallback is unavailable.');
        }

        $escaped = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
        $filter = sprintf(
            '(&(|(objectClass=person)(objectClass=inetOrgPerson)(objectClass=supannPerson))(|(cn=*%1$s*)(displayName=*%1$s*)(sn=*%1$s*)(givenName=*%1$s*)(uid=*%1$s*)(mail=*%1$s*)))',
            $escaped
        );

        $command = [
            $binary,
            '-x',
            '-H',
            sprintf('ldap://%s:%d', $this->host, $this->port),
            '-b',
            $this->baseDn,
            '-LLL',
            '-z',
            (string) $limit,
        ];

        if ($this->useTls) {
            $command[] = '-ZZ';
        }

        if ($this->bindDn) {
            $command[] = '-D';
            $command[] = $this->bindDn;
            $command[] = '-w';
            $command[] = (string) $this->bindPassword;
        }

        $command[] = $filter;
        array_push(
            $command,
            'uid',
            'supannAliasLogin',
            'cn',
            'displayName',
            'sn',
            'givenName',
            'mail',
            'title',
            'employeeType',
            'ou',
            'departmentNumber',
            'description'
        );

        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to execute ldapsearch fallback.');
        }

        $output = stream_get_contents($pipes[1]) ?: '';
        $error = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if (!in_array($exitCode, [0, 4], true)) {
            throw new \RuntimeException(trim($error) ?: 'ldapsearch fallback failed.');
        }

        return $this->mapLdif($output);
    }

    /**
     * @return list<PersonnelEntry>
     */
    private function mapLdif(string $ldif): array
    {
        $entries = preg_split('/\R{2,}/', trim($ldif)) ?: [];
        $personnel = [];

        foreach ($entries as $rawEntry) {
            $entry = [];
            foreach (preg_split('/\R/', $rawEntry) ?: [] as $line) {
                if (!str_contains($line, ':')) {
                    continue;
                }

                [$attribute, $value] = explode(':', $line, 2);
                $attribute = strtolower(trim($attribute));
                $value = ltrim($value);

                if (str_starts_with($value, ':')) {
                    $decoded = base64_decode(ltrim(substr($value, 1)), true);
                    $value = $decoded !== false ? $decoded : '';
                }

                $entry[$attribute][] = $value;
            }

            $mapped = $this->mapEntry($entry);
            if ($mapped !== null) {
                $personnel[] = $mapped;
            }
        }

        usort($personnel, static fn(PersonnelEntry $a, PersonnelEntry $b): int => strcasecmp($a->displayName, $b->displayName));

        return $personnel;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function mapEntry(array $entry): ?PersonnelEntry
    {
        $firstName = $this->firstValue($entry, 'givenname');
        $lastName = $this->firstValue($entry, 'sn');
        $displayName = $this->firstValue($entry, 'displayname')
            ?? $this->firstValue($entry, 'cn')
            ?? trim((string) $firstName . ' ' . (string) $lastName);

        if ($displayName === '') {
            return null;
        }

        $username = $this->firstValue($entry, 'uid') ?? $this->firstValue($entry, 'supannaliaslogin');
        $email = $this->firstValue($entry, 'mail');

        return new PersonnelEntry(
            id: $email ?? $username ?? md5($displayName),
            displayName: $displayName,
            lastName: $lastName,
            firstName: $firstName,
            email: $email,
            username: $username,
            title: $this->firstValue($entry, 'title') ?? $this->firstValue($entry, 'employeetype'),
            service: $this->firstValue($entry, 'ou'),
            structure: $this->firstValue($entry, 'departmentnumber') ?? $this->firstValue($entry, 'description'),
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstValue(array $entry, string $attribute): ?string
    {
        $values = $entry[strtolower($attribute)] ?? null;
        if (!is_array($values) || !isset($values[0])) {
            return null;
        }

        $value = trim((string) $values[0]);

        return $value === '' ? null : $value;
    }
}
