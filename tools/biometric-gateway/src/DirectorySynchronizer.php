<?php

declare(strict_types=1);

namespace SchoolLift\BiometricGateway;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

/** Reconciles a complete SchoolLift roster snapshot into ZKBio Time. */
final class DirectorySynchronizer
{
    private GatewayStore $store;
    private ZkBioClient $provider;
    private SchoolLiftClient $schoolLift;
    private Clock $clock;
    private JsonLogger $logger;
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(
        GatewayStore $store,
        ZkBioClient $provider,
        SchoolLiftClient $schoolLift,
        Clock $clock,
        JsonLogger $logger,
        array $config
    ) {
        $this->store = $store;
        $this->provider = $provider;
        $this->schoolLift = $schoolLift;
        $this->clock = $clock;
        $this->logger = $logger;
        $this->config = $config;
    }

    /** @return array<string, mixed> */
    public function run(bool $force = false): array
    {
        $started = $this->clock->now();
        $interval = (int) $this->config['directory']['default_interval_seconds'];
        if (!$force && !$this->store->directorySyncDue($started, $interval)) {
            return ['ran' => false, 'status' => 'not_due'];
        }

        $snapshot = null;
        try {
            $snapshot = $this->schoolLift->fetchDirectory((int) $this->config['request_timeout_seconds']);
            $this->validateSnapshot($snapshot);
            $mode = (string) $snapshot['mode'];
            if ($mode === 'off') {
                $this->store->recordDirectorySync('off', null, $this->clock->now());
                return ['ran' => true, 'status' => 'off', 'mode' => 'off'];
            }
            if ($mode === 'active' && $this->config['provider']['area_ids'] === []) {
                throw new RuntimeException('Active directory synchronization requires at least one configured ZKBio area ID.');
            }

            $departments = $this->provider->listDepartments((int) $this->config['request_timeout_seconds']);
            $positions = $this->provider->listPositions((int) $this->config['request_timeout_seconds']);
            $employees = $this->provider->listEmployees((int) $this->config['request_timeout_seconds']);
            $departmentIds = $this->reconcileDepartments($snapshot['departments'], $departments, $mode);
            $positionIds = $this->reconcilePositions($snapshot['positions'], $positions, $mode);

            $results = [];
            $counts = $this->emptyCounts((int) $snapshot['counts']['people']);
            foreach ($snapshot['conflicts'] as $conflict) {
                $counts['conflicts']++;
                $results[] = $this->resultItem($conflict, 'conflict', null, null, null, (string) $conflict['reason']);
            }

            $employeesById = $this->indexById($employees);
            $employeesByCode = $this->indexEmployeesByCode($employees);
            $resyncIds = [];
            foreach ($snapshot['people'] as $person) {
                try {
                    $result = $this->reconcilePerson(
                        $person,
                        $mode,
                        $departmentIds,
                        $positionIds,
                        $employeesById,
                        $employeesByCode
                    );
                    $results[] = $result;
                    $counts[$this->countKey((string) $result['status'])]++;
                    if (in_array($result['status'], ['created', 'adopted', 'updated'], true)
                        && $result['provider_person_id'] !== null) {
                        $resyncIds[] = $result['provider_person_id'];
                    }
                } catch (Throwable $error) {
                    $counts['failed']++;
                    $results[] = $this->resultItem($person, 'failed', null,
                        (string) ($person['desired_hash'] ?? ''), null, $this->bounded($error->getMessage()));
                }
            }

            $deleteCount = count($snapshot['tombstones']);
            $counts['delete_candidates'] = $deleteCount;
            $managedTotal = max(1, count($snapshot['people']) + $deleteCount);
            $largeDelete = $deleteCount > (int) $this->config['directory']['maximum_delete_count']
                || (($deleteCount * 100) / $managedTotal) > (int) $this->config['directory']['maximum_delete_percent'];
            $requiresApproval = $mode === 'active' && $largeDelete && empty($snapshot['delete_approved']);

            foreach ($snapshot['tombstones'] as $tombstone) {
                if ($mode === 'preview') {
                    $counts['deleted']++;
                    $results[] = $this->resultItem($tombstone, 'deleted',
                        $tombstone['provider_person_id'] ?? null, null, null, null);
                    continue;
                }
                if ($requiresApproval) {
                    $results[] = $this->resultItem($tombstone, 'pending_approval',
                        $tombstone['provider_person_id'] ?? null, null, null,
                        'The deletion batch exceeds the configured safety threshold.');
                    continue;
                }
                try {
                    $providerId = $this->resolveTombstoneProviderId($tombstone, $employeesById, $employeesByCode);
                    if ($providerId !== null) {
                        $this->provider->deleteEmployee($providerId, (int) $this->config['request_timeout_seconds']);
                    }
                    $counts['deleted']++;
                    $results[] = $this->resultItem($tombstone, 'deleted', $providerId, null, null, null);
                    $this->store->saveDirectoryPerson([
                        'person_key' => $tombstone['person_key'],
                        'subject_type' => $tombstone['subject_type'],
                        'subject_key' => $tombstone['subject_key'],
                        'subject_id' => $tombstone['subject_id'] ?? null,
                        'emp_code' => $tombstone['emp_code'],
                        'provider_person_id' => null,
                        'desired_hash' => null,
                        'applied_hash' => null,
                        'state' => 'deleted',
                        'last_seen_snapshot' => $snapshot['snapshot_hash'],
                        'last_error' => null,
                    ], $this->clock->now());
                } catch (Throwable $error) {
                    $counts['failed']++;
                    $results[] = $this->resultItem($tombstone, 'failed',
                        $tombstone['provider_person_id'] ?? null, null, null, $this->bounded($error->getMessage()));
                }
            }

            $resyncError = null;
            if ($mode === 'active' && $resyncIds !== [] && !empty($this->config['provider']['resync_to_device'])) {
                try {
                    $this->provider->resyncEmployees(array_values(array_unique($resyncIds)), (int) $this->config['request_timeout_seconds']);
                } catch (Throwable $error) {
                    $resyncError = 'Personnel were saved, but ZKBio device resync failed: ' . $this->bounded($error->getMessage());
                    $counts['failed']++;
                }
            }

            $status = $mode === 'preview' ? 'preview'
                : ($requiresApproval ? 'awaiting_approval'
                    : (($counts['failed'] > 0 || $counts['conflicts'] > 0) ? 'partial' : 'success'));
            $completed = $this->clock->now();
            $payload = $this->resultPayload($snapshot, $started, $completed, $status, $counts, $results, $requiresApproval, $resyncError);
            $this->sendResult($payload);
            $this->store->recordDirectorySync($status, $resyncError, $completed);
            $this->logger->log('info', 'SchoolLift directory synchronization completed.', [
                'mode' => $mode,
                'status' => $status,
                'snapshot_id' => $snapshot['snapshot_id'],
                'summary' => $counts,
            ]);
            return [
                'ran' => true,
                'mode' => $mode,
                'status' => $status,
                'snapshot_id' => $snapshot['snapshot_id'],
                'summary' => $counts,
                'requires_delete_approval' => $requiresApproval,
                'error' => $resyncError,
            ];
        } catch (Throwable $error) {
            $message = $this->bounded($error->getMessage());
            $completed = $this->clock->now();
            $this->store->recordDirectorySync('failed', $message, $completed);
            if (is_array($snapshot) && isset($snapshot['snapshot_id'], $snapshot['snapshot_hash'], $snapshot['mode'])) {
                try {
                    $counts = $this->emptyCounts(isset($snapshot['counts']['people']) ? (int) $snapshot['counts']['people'] : 0);
                    $counts['failed'] = max(1, $counts['desired']);
                    $this->sendResult($this->resultPayload(
                        $snapshot, $started, $completed, 'failed', $counts, [], false, $message
                    ));
                } catch (Throwable $reportError) {
                    $message .= ' Result reporting also failed: ' . $this->bounded($reportError->getMessage());
                }
            }
            $this->logger->log('error', 'SchoolLift directory synchronization failed.', ['error' => $message]);
            return ['ran' => true, 'status' => 'failed', 'error' => $message];
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function validateSnapshot(array $snapshot): void
    {
        foreach (['complete', 'mode', 'snapshot_id', 'snapshot_hash', 'counts', 'departments', 'positions', 'people', 'tombstones', 'conflicts'] as $field) {
            if (!array_key_exists($field, $snapshot)) {
                throw new RuntimeException('SchoolLift directory snapshot is missing ' . $field . '.');
            }
        }
        if ($snapshot['complete'] !== true || !in_array($snapshot['mode'], ['off', 'preview', 'active'], true)
            || !is_string($snapshot['snapshot_id']) || !preg_match('/^[a-f0-9]{32}$/', $snapshot['snapshot_id'])
            || !is_string($snapshot['snapshot_hash']) || !preg_match('/^[a-f0-9]{64}$/', $snapshot['snapshot_hash'])) {
            throw new RuntimeException('SchoolLift returned an incomplete or invalid directory snapshot.');
        }
        foreach (['counts', 'departments', 'positions', 'people', 'tombstones', 'conflicts'] as $field) {
            if (!is_array($snapshot[$field])) {
                throw new RuntimeException('SchoolLift directory snapshot field ' . $field . ' must be an array.');
            }
        }
        $content = [
            'departments' => $snapshot['departments'],
            'positions' => $snapshot['positions'],
            'people' => $snapshot['people'],
            'tombstones' => $snapshot['tombstones'],
            'conflicts' => $snapshot['conflicts'],
        ];
        $actualHash = hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (!hash_equals($snapshot['snapshot_hash'], $actualHash)
            || !hash_equals($snapshot['snapshot_id'], substr($actualHash, 0, 32))) {
            throw new RuntimeException('SchoolLift directory snapshot checksum does not match its contents.');
        }
        if ((int) ($snapshot['counts']['people'] ?? -1) !== count($snapshot['people'])
            || (int) ($snapshot['counts']['tombstones'] ?? -1) !== count($snapshot['tombstones'])
            || (int) ($snapshot['counts']['conflicts'] ?? -1) !== count($snapshot['conflicts'])) {
            throw new RuntimeException('SchoolLift directory snapshot totals do not match its contents.');
        }
    }

    /** @param array<int, array<string, mixed>> $desired @param array<int, array<string, mixed>> $existing
     *  @return array<string, string|null>
     */
    private function reconcileDepartments(array $desired, array $existing, string $mode): array
    {
        $byCode = $this->indexGroupsByCode($existing, 'dept_code');
        $ids = [];
        foreach ($desired as $group) {
            $code = (string) ($group['code'] ?? '');
            $name = $this->truncate((string) ($group['name'] ?? ''), 100);
            if ($code === '' || $name === '') {
                throw new RuntimeException('SchoolLift supplied an invalid department.');
            }
            if (isset($byCode[$code]) && count($byCode[$code]) > 1) {
                throw new RuntimeException('ZKBio contains duplicate department code ' . $code . '.');
            }
            $parentCode = $group['parent_code'] ?? null;
            $parentId = $parentCode !== null ? ($ids[(string) $parentCode] ?? null) : null;
            $current = isset($byCode[$code][0]) ? $byCode[$code][0] : null;
            $payload = ['dept_code' => $code, 'dept_name' => $name, 'parent_dept' => $parentId === null ? null : (int) $parentId];
            $desiredHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
            if ($current === null) {
                if ($mode === 'active') {
                    $current = $this->provider->createDepartment($payload, (int) $this->config['request_timeout_seconds']);
                } else {
                    // Preview never writes provider groups. A stable placeholder
                    // still lets personnel reconciliation calculate the diff.
                    $ids[$code] = '0';
                    continue;
                }
            } elseif ($mode === 'active' && !$this->departmentMatches($current, $payload)) {
                $current = $this->provider->updateDepartment($this->resourceId($current), $payload, (int) $this->config['request_timeout_seconds']);
            }
            $id = $this->resourceId($current);
            $ids[$code] = $id;
            $this->store->saveDirectoryGroup('department', $code, $id, $desiredHash, $this->clock->now());
        }
        return $ids;
    }

    /** @param array<int, array<string, mixed>> $desired @param array<int, array<string, mixed>> $existing
     *  @return array<string, string|null>
     */
    private function reconcilePositions(array $desired, array $existing, string $mode): array
    {
        $byCode = $this->indexGroupsByCode($existing, 'position_code');
        $ids = [];
        foreach ($desired as $group) {
            $code = (string) ($group['code'] ?? '');
            $name = $this->truncate((string) ($group['name'] ?? ''), 100);
            if ($code === '' || $name === '') {
                throw new RuntimeException('SchoolLift supplied an invalid position.');
            }
            if (isset($byCode[$code]) && count($byCode[$code]) > 1) {
                throw new RuntimeException('ZKBio contains duplicate position code ' . $code . '.');
            }
            $current = isset($byCode[$code][0]) ? $byCode[$code][0] : null;
            $payload = ['position_code' => $code, 'position_name' => $name, 'parent_position' => null];
            $desiredHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
            if ($current === null) {
                if ($mode === 'active') {
                    $current = $this->provider->createPosition($payload, (int) $this->config['request_timeout_seconds']);
                } else {
                    $ids[$code] = '0';
                    continue;
                }
            } elseif ($mode === 'active' && !$this->positionMatches($current, $payload)) {
                $current = $this->provider->updatePosition($this->resourceId($current), $payload, (int) $this->config['request_timeout_seconds']);
            }
            $id = $this->resourceId($current);
            $ids[$code] = $id;
            $this->store->saveDirectoryGroup('position', $code, $id, $desiredHash, $this->clock->now());
        }
        return $ids;
    }

    /**
     * @param array<string, mixed> $person
     * @param array<string, string|null> $departmentIds
     * @param array<string, string|null> $positionIds
     * @param array<string, array<string, mixed>> $employeesById
     * @param array<string, array<int, array<string, mixed>>> $employeesByCode
     * @return array<string, mixed>
     */
    private function reconcilePerson(
        array $person,
        string $mode,
        array $departmentIds,
        array $positionIds,
        array $employeesById,
        array $employeesByCode
    ): array {
        $this->validatePerson($person);
        $code = $this->normalizeCode((string) $person['emp_code']);
        $providerId = isset($person['provider_person_id']) && $person['provider_person_id'] !== null
            ? (string) $person['provider_person_id'] : null;
        $local = $this->store->directoryPerson((string) $person['person_key']);
        if ($providerId === null && is_array($local) && !empty($local['provider_person_id'])) {
            $providerId = (string) $local['provider_person_id'];
        }
        $codeMatches = $employeesByCode[$code] ?? [];
        if (count($codeMatches) > 1) {
            return $this->resultItem($person, 'conflict', null, (string) $person['desired_hash'], null,
                'ZKBio contains more than one employee with this code.');
        }
        $current = $providerId !== null && isset($employeesById[$providerId]) ? $employeesById[$providerId] : null;
        if ($current === null) {
            $current = $codeMatches[0] ?? null;
        }

        $departmentCode = (string) $person['department_code'];
        $positionCode = (string) $person['position_code'];
        $payload = $this->employeePayload(
            $person,
            $departmentIds[$departmentCode] ?? null,
            $positionIds[$positionCode] ?? null
        );
        $appliedHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $wasClaimed = $providerId !== null || is_array($local);

        if ($current === null) {
            if ($mode === 'preview') {
                return $this->resultItem($person, 'created', null, (string) $person['desired_hash'], $appliedHash, null);
            }
            $created = $this->provider->createEmployee($payload, (int) $this->config['request_timeout_seconds']);
            $providerId = $this->resourceId($created);
            $status = 'created';
        } else {
            $providerId = $this->resourceId($current);
            $matches = $this->employeeMatches($current, $payload);
            if (!$matches && $mode === 'active') {
                $this->provider->updateEmployee($providerId, $payload, (int) $this->config['request_timeout_seconds']);
            }
            $status = !$wasClaimed ? 'adopted' : ($matches ? 'unchanged' : 'updated');
        }

        if ($mode === 'active') {
            $this->store->saveDirectoryPerson([
                'person_key' => $person['person_key'],
                'subject_type' => $person['subject_type'],
                'subject_key' => $person['subject_key'],
                'subject_id' => $person['subject_id'],
                'emp_code' => $code,
                'provider_person_id' => $providerId,
                'desired_hash' => $person['desired_hash'],
                'applied_hash' => $appliedHash,
                'state' => 'synced',
                'last_seen_snapshot' => null,
                'last_error' => null,
            ], $this->clock->now());
        }
        return $this->resultItem($person, $status, $providerId, (string) $person['desired_hash'], $appliedHash, null);
    }

    /** @param array<string, mixed> $person */
    private function validatePerson(array $person): void
    {
        foreach (['person_key', 'subject_type', 'subject_key', 'subject_id', 'emp_code', 'department_code', 'position_code', 'desired_hash'] as $field) {
            if (!array_key_exists($field, $person)) {
                throw new RuntimeException('SchoolLift person is missing ' . $field . '.');
            }
        }
        if (!in_array($person['subject_type'], ['student', 'staff'], true)
            || (int) $person['subject_key'] < 1 || (int) $person['subject_id'] < 1
            || $person['person_key'] !== $person['subject_type'] . ':' . (int) $person['subject_key']
            || !preg_match('/^[a-f0-9]{64}$/', (string) $person['desired_hash'])) {
            throw new RuntimeException('SchoolLift person identity is invalid.');
        }
    }

    /** @param array<string, mixed> $person @return array<string, mixed> */
    private function employeePayload(array $person, ?string $departmentId, ?string $positionId): array
    {
        if ($departmentId === null || $positionId === null) {
            throw new RuntimeException('The person references a ZKBio department or position that is not available.');
        }
        $gender = strtoupper(substr(trim((string) ($person['gender'] ?? '')), 0, 1));
        if (!in_array($gender, ['M', 'F'], true)) {
            $gender = 'S';
        }
        $payload = [
            'emp_code' => $this->normalizeCode((string) $person['emp_code']),
            'first_name' => $this->truncate((string) ($person['first_name'] ?? ''), 25),
            'last_name' => $this->truncate((string) ($person['last_name'] ?? ''), 25),
            'gender' => $gender,
            'mobile' => $this->truncate((string) ($person['mobile'] ?? ''), 20),
            'email' => $this->truncate((string) ($person['email'] ?? ''), 50),
            'department' => (int) $departmentId,
            'position' => (int) $positionId,
            'area' => array_values($this->config['provider']['area_ids']),
            'enable_att' => true,
            'app_status' => 1,
        ];
        if (!empty($person['hire_date'])) {
            $payload['hire_date'] = (string) $person['hire_date'];
        }
        return $payload;
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $payload */
    private function employeeMatches(array $current, array $payload): bool
    {
        foreach (['emp_code', 'first_name', 'last_name', 'gender', 'mobile', 'email'] as $field) {
            if (trim((string) ($current[$field] ?? '')) !== trim((string) $payload[$field])) {
                return false;
            }
        }
        if ($this->nestedId($current['department'] ?? null) !== (string) $payload['department']
            || $this->nestedId($current['position'] ?? null) !== (string) $payload['position']) {
            return false;
        }
        $areas = $current['area'] ?? $current['areas'] ?? [];
        $currentAreaIds = [];
        if (is_array($areas)) {
            foreach ($areas as $area) {
                $id = $this->nestedId($area);
                if ($id !== null) {
                    $currentAreaIds[] = (int) $id;
                }
            }
        }
        sort($currentAreaIds);
        $desiredAreaIds = array_map('intval', $payload['area']);
        sort($desiredAreaIds);
        return $currentAreaIds === $desiredAreaIds;
    }

    /** @param array<string, mixed> $tombstone
     *  @param array<string, array<string, mixed>> $employeesById
     *  @param array<string, array<int, array<string, mixed>>> $employeesByCode
     */
    private function resolveTombstoneProviderId(array $tombstone, array $employeesById, array $employeesByCode): ?string
    {
        $providerId = isset($tombstone['provider_person_id']) && $tombstone['provider_person_id'] !== null
            ? (string) $tombstone['provider_person_id'] : null;
        if ($providerId !== null && isset($employeesById[$providerId])) {
            return $providerId;
        }
        $matches = $employeesByCode[$this->normalizeCode((string) $tombstone['emp_code'])] ?? [];
        if (count($matches) > 1) {
            throw new RuntimeException('ZKBio contains duplicate employees for the deletion code.');
        }
        return isset($matches[0]) ? $this->resourceId($matches[0]) : null;
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, array<string, mixed>> */
    private function indexById(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            if (isset($item['id'])) {
                $indexed[(string) $item['id']] = $item;
            }
        }
        return $indexed;
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, array<int, array<string, mixed>>> */
    private function indexEmployeesByCode(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $code = $this->normalizeCode((string) ($item['emp_code'] ?? ''));
            if ($code !== '') {
                $indexed[$code][] = $item;
            }
        }
        return $indexed;
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, array<int, array<string, mixed>>> */
    private function indexGroupsByCode(array $items, string $field): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $code = trim((string) ($item[$field] ?? ''));
            if ($code !== '') {
                $indexed[$code][] = $item;
            }
        }
        return $indexed;
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $payload */
    private function departmentMatches(array $current, array $payload): bool
    {
        return trim((string) ($current['dept_code'] ?? '')) === $payload['dept_code']
            && trim((string) ($current['dept_name'] ?? '')) === $payload['dept_name']
            && $this->nestedId($current['parent_dept'] ?? null) === ($payload['parent_dept'] === null ? null : (string) $payload['parent_dept']);
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $payload */
    private function positionMatches(array $current, array $payload): bool
    {
        return trim((string) ($current['position_code'] ?? '')) === $payload['position_code']
            && trim((string) ($current['position_name'] ?? '')) === $payload['position_name'];
    }

    /** @param mixed $value */
    private function nestedId($value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }
        return $value === null || $value === '' ? null : (string) $value;
    }

    /** @param array<string, mixed> $resource */
    private function resourceId(array $resource): string
    {
        $id = $resource['id'] ?? ($resource['data']['id'] ?? null);
        if ($id === null || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', (string) $id)) {
            throw new RuntimeException('ZKBio response did not contain a valid resource ID.');
        }
        return (string) $id;
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function resultItem(
        array $source,
        string $status,
        ?string $providerId,
        ?string $desiredHash,
        ?string $appliedHash,
        ?string $error
    ): array {
        return [
            'person_key' => (string) $source['person_key'],
            'subject_type' => (string) $source['subject_type'],
            'subject_key' => (int) $source['subject_key'],
            'subject_id' => isset($source['subject_id']) ? (int) $source['subject_id'] : null,
            'emp_code' => $this->normalizeCode((string) $source['emp_code']),
            'provider_person_id' => $providerId,
            'desired_hash' => $desiredHash !== '' ? $desiredHash : null,
            'applied_hash' => $appliedHash !== '' ? $appliedHash : null,
            'status' => $status,
            'error' => $error,
        ];
    }

    /** @return array<string, int> */
    private function emptyCounts(int $desired): array
    {
        return [
            'desired' => max(0, $desired), 'created' => 0, 'adopted' => 0,
            'updated' => 0, 'deleted' => 0, 'unchanged' => 0,
            'conflicts' => 0, 'failed' => 0, 'delete_candidates' => 0,
        ];
    }

    private function countKey(string $status): string
    {
        return $status === 'conflict' ? 'conflicts' : $status;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, int> $counts
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function resultPayload(
        array $snapshot,
        DateTimeImmutable $started,
        DateTimeImmutable $completed,
        string $status,
        array $counts,
        array $items,
        bool $requiresApproval,
        ?string $error
    ): array {
        $counts['requires_delete_approval'] = $requiresApproval;
        return [
            'gateway_id' => (string) $this->config['gateway_id'],
            'snapshot_id' => (string) $snapshot['snapshot_id'],
            'snapshot_hash' => (string) $snapshot['snapshot_hash'],
            'mode' => (string) $snapshot['mode'],
            'status' => $status,
            'started_at' => $started->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'completed_at' => $completed->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'summary' => $counts,
            'items' => $items,
            'error_summary' => $error,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function sendResult(array $payload): void
    {
        $response = $this->schoolLift->reportDirectory($payload, (int) $this->config['request_timeout_seconds']);
        if ($response['status'] < 200 || $response['status'] > 299
            || !is_array($response['json']) || empty($response['json']['success'])) {
            $message = is_array($response['json']) ? (string) ($response['json']['message'] ?? '') : '';
            throw new UpstreamException('SchoolLift rejected the directory result (HTTP ' . $response['status'] . ')'
                . ($message !== '' ? ': ' . $message : ''), $response['status']);
        }
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function truncate(string $value, int $length): string
    {
        $value = trim($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    private function bounded(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';
        return $this->truncate($message, 500);
    }
}
