<?php

namespace OpenDemat\Core\EsupSignature\DTO;

enum SignType: string
{
    case HiddenVisa    = 'hiddenVisa';
    case Visa          = 'visa';
    case PdfImageStamp = 'pdfImageStamp';
    case CertSign      = 'certSign';
    case NexuSign      = 'nexuSign';
    // Valeur canonique introduite en v1.36 — les trois cas ci-dessus y sont normalisés
    case Signature     = 'signature';
}
