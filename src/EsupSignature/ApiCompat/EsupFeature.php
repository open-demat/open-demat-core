<?php

namespace OpenDemat\Core\EsupSignature\ApiCompat;

enum EsupFeature
{
    case ConvertToPDFA;
    case SingleSignWithAnnotation;
    case SignRequestParamsInStep;
    case AttachmentFiles;
    case JwtAuth;
}
