<?php

namespace OpenDemat\Core\EsupSignature\Service;

use OpenDemat\Core\EsupSignature\DTO\SignRequestOptions;
use OpenDemat\Core\EsupSignature\DTO\WorkflowStep;

class WorkflowService
{
    public function __construct(
        private readonly EsupSignatureApiClient $client,
        private readonly EsupSignatureServerInfo $serverInfo,
    ) {
    }

    /**
     * @param WorkflowStep[] $steps
     */
    public function start(
        int                $workflowId,
        string             $filePath,
        string             $fileName,
        string             $createByEppn,
        array              $steps   = [],
        SignRequestOptions $options = new SignRequestOptions(),
    ): array {
        $adapter = $this->serverInfo->getAdapter();

        $fields = [
            'createByEppn'   => $createByEppn,
            'sendEmailAlert' => $options->sendEmail ? 'true' : 'false',
            'json'           => 'true',
        ];

        if (!empty($steps)) {
            $normalizedSteps = array_map(function (WorkflowStep $s) use ($adapter): array {
                $arr = $s->toArray();
                $arr['signType'] = $adapter->normalizeSignType($s->signType);
                return $arr;
            }, $steps);

            $fields['stepsJsonString'] = json_encode($normalizedSteps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if ($options->title !== null)     { $fields['title']     = $options->title; }
        if ($options->comment !== null)   { $fields['comment']   = $options->comment; }
        if ($options->targetUrl !== null) { $fields['targetUrl'] = $options->targetUrl; }

        foreach ($options->targetEmails as $email) { $fields['targetEmails[]'] = $email; }
        foreach ($options->targetUrls   as $url)   { $fields['targetUrls[]']   = $url; }

        return $this->client->postFile("/ws/workflows/{$workflowId}/new", $filePath, $fileName, $fields);
    }

    public function listAll(): array
    {
        return (array) $this->client->get('/ws/workflows/all');
    }

    public function getDatas(int $id): array
    {
        return (array) $this->client->get("/ws/workflows/get-datas/{$id}");
    }

    public function exportDatasJson(int $id): array
    {
        return (array) $this->client->get("/ws/export/workflow/{$id}/datas/json");
    }
}
