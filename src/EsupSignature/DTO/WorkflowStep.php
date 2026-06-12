<?php

namespace OpenDemat\Core\EsupSignature\DTO;

/**
 * Étape d'un circuit de signature.
 *
 * Contient l'union de tous les champs de toutes les versions supportées.
 * Les champs propres à une version (ex: v1.36+) sont nullable et exclus
 * de la sérialisation JSON quand ils sont null.
 *
 * @see EsupApiAdapterInterface::normalizeWorkflowStep() pour la normalisation avant envoi HTTP
 */
readonly class WorkflowStep
{
    /**
     * @param Recipient[] $recipients
     * @param string[]    $recipientsCCEmails
     */
    public function __construct(
        public int      $stepNumber,
        public SignType $signType,
        public array    $recipients,
        public ?string  $title                = null,
        public ?string  $description          = null,
        public ?string  $comment              = null,
        public bool     $allSignToComplete    = false,
        public bool     $multiSign            = false,
        public bool     $autoSign             = false,
        public bool     $repeatable           = false,
        public bool     $changeable           = false,
        public bool     $userSignFirst        = false,
        public bool     $forceAllSign         = false,
        public bool     $attachmentRequire    = false,
        public bool     $attachmentAlert      = false,
        public int      $signLevel            = 0,
        public int      $maxRecipients        = 0,
        public ?SignType $repeatableSignType   = null,
        public array    $recipientsCCEmails   = [],
        // Champs v1.36+
        public ?bool    $convertToPDFA             = null,
        public ?bool    $singleSignWithAnnotation   = null,
        public ?string  $minSignLevel               = null,
        public ?string  $maxSignLevel               = null,
    ) {
    }

    public function toArray(): array
    {
        $step = [
            'stepNumber'        => $this->stepNumber,
            'signType'          => $this->signType->value,
            'recipients'        => array_map(fn(Recipient $r) => $r->toArray(), $this->recipients),
            'allSignToComplete' => $this->allSignToComplete,
            'multiSign'         => $this->multiSign,
            'autoSign'          => $this->autoSign,
            'repeatable'        => $this->repeatable,
            'changeable'        => $this->changeable,
            'userSignFirst'     => $this->userSignFirst,
            'forceAllSign'      => $this->forceAllSign,
            'attachmentRequire' => $this->attachmentRequire,
            'attachmentAlert'   => $this->attachmentAlert,
            'signLevel'         => $this->signLevel,
            'maxRecipients'     => $this->maxRecipients,
        ];

        if ($this->title !== null)              { $step['title']                    = $this->title; }
        if ($this->description !== null)        { $step['description']              = $this->description; }
        if ($this->comment !== null)            { $step['comment']                  = $this->comment; }
        if ($this->repeatableSignType !== null) { $step['repeatableSignType']       = $this->repeatableSignType->value; }
        if (!empty($this->recipientsCCEmails))  { $step['recipientsCCEmails']       = $this->recipientsCCEmails; }
        if ($this->convertToPDFA !== null)      { $step['convertToPDFA']            = $this->convertToPDFA; }
        if ($this->singleSignWithAnnotation !== null) { $step['singleSignWithAnnotation'] = $this->singleSignWithAnnotation; }
        if ($this->minSignLevel !== null)       { $step['minSignLevel']             = $this->minSignLevel; }
        if ($this->maxSignLevel !== null)       { $step['maxSignLevel']             = $this->maxSignLevel; }

        return $step;
    }
}
