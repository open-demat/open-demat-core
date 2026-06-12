<?php

namespace OpenDemat\Core\EsupSignature\Service;

use OpenDemat\Core\EsupSignature\DTO\FormDatas;
use OpenDemat\Core\EsupSignature\DTO\SignRequestOptions;

class FormService
{
    public function __construct(
        private readonly EsupSignatureApiClient $client,
    ) {
    }

    public function start(
        int                $formId,
        string             $createByEppn,
        FormDatas          $formDatas,
        SignRequestOptions $options = new SignRequestOptions(),
    ): array {
        $fields = [
            'createByEppn'   => $createByEppn,
            'sendEmailAlert' => $options->sendEmail ? 'true' : 'false',
            'json'           => 'true',
        ];

        if (!$formDatas->isEmpty())       { $fields['formDatas'] = $formDatas->toJson(); }
        if ($options->title !== null)     { $fields['title']     = $options->title; }
        if ($options->comment !== null)   { $fields['comment']   = $options->comment; }
        if ($options->targetUrl !== null) { $fields['targetUrl'] = $options->targetUrl; }

        foreach ($options->targetEmails as $email) { $fields['targetEmails[]'] = $email; }
        foreach ($options->targetUrls   as $url)   { $fields['targetUrls[]']   = $url; }

        return $this->client->post("/ws/forms/{$formId}/new", $fields);
    }

    public function listAll(): array
    {
        return (array) $this->client->get('/ws/forms/all');
    }

    public function getDatas(int $id): array
    {
        return (array) $this->client->get("/ws/workflows/get-datas/{$id}");
    }
}
