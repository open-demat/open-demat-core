<?php

namespace OpenDemat\Core\EsupSignature\Exception;

class EsLoginRedirectException extends \RuntimeException
{
    public function __construct(string $path, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Le serveur ES a retourné la page de login CAS sur "%s" — '
                . 'cet endpoint requiert une session admin ; la clé X-Api-Key n\'est pas acceptée sur cette route.',
                $path
            ),
            $code,
            $previous
        );
    }
}
