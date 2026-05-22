<?php

declare(strict_types=1);

namespace HayBTech\Exceptions;

/**
 * Thrown when webhook signature verification fails.
 *
 * Do NOT return 200 to HayBTech when this exception is raised — return 400
 * so the delivery is logged as failed and the operations team can investigate.
 * Returning 200 on an invalid signature silences real security incidents.
 */
class SignatureException extends HayBTechException {}
