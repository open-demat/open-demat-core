<?php

namespace OpenDemat\Core\EsupSignature\Service;

use OpenDemat\Core\EsupSignature\DTO\SignRequestOptions;
use OpenDemat\Core\EsupSignature\DTO\SignRequestStatus;
use OpenDemat\Core\EsupSignature\DTO\WorkflowStep;

class SignRequestService
{
    public function __construct(
        private readonly EsupSignatureApiClient $client,
        private readonly EsupSignatureServerInfo $serverInfo,
    ) {
    }

    /**
     * @param WorkflowStep[] $steps
     */
    public function create(
        string             $filePath,
        string             $fileName,
        string             $createByEppn,
        array              $steps,
        SignRequestOptions $options = new SignRequestOptions(),
    ): array {
        $adapter = $this->serverInfo->getAdapter();

        $fields = [
            'createByEppn'   => $createByEppn,
            'pending'        => $options->pending   ? 'true' : 'false',
            'sendEmailAlert' => $options->sendEmail ? 'true' : 'false',
        ];

        if ($options->title     !== null) { $fields['title']     = $options->title; }
        if ($options->comment   !== null) { $fields['comment']   = $options->comment; }
        if ($options->targetUrl !== null) { $fields['targetUrl'] = $options->targetUrl; }

        $normalizedSteps = array_map(function (WorkflowStep $s) use ($adapter): array {
            $arr = $s->toArray();
            $arr['signType'] = $adapter->normalizeSignType($s->signType);
            return $arr;
        }, $steps);

        $fields['stepsJsonString'] = json_encode($normalizedSteps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ($options->targetEmails as $email) { $fields['targetEmails[]']       = $email; }
        foreach ($options->ccEmails     as $email) { $fields['recipientsCCEmails[]'] = $email; }

        return $this->client->postFile('/ws/signrequests/new', $filePath, $fileName, $fields);
    }

    public function getStatus(string $id): SignRequestStatus
    {
        $raw = $this->client->get("/ws/signrequests/status/{$id}");
        $value = is_array($raw) ? ($raw['status'] ?? '') : (string) $raw;

        return SignRequestStatus::from($value);
    }

    public function getLastFile(string $id): string
    {
        return (string) $this->client->get("/ws/signrequests/get-last-file/{$id}");
    }

    public function getAuditTrail(string $id): string
    {
        return (string) $this->client->get("/ws/signrequests/audit-trail/{$id}");
    }

    public function softDelete(string $id): void
    {
        $this->client->delete("/ws/signrequests/soft/{$id}");
    }

    public function delete(string $id): void
    {
        $this->client->delete("/ws/signrequests/{$id}");
    }

    public function listAll(): array
    {
        return (array) $this->client->get('/ws/signrequests/all');
    }
}
