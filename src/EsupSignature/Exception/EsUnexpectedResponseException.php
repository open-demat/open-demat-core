<?php

namespace OpenDemat\Core\EsupSignature\Exception;

class EsUnexpectedResponseException extends \RuntimeException
{
    public function __construct(string $path, string $preview, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Réponse non-JSON reçue depuis "%s" (début : %s…)', $path, $preview),
            $code,
            $previous
        );
    }
}
