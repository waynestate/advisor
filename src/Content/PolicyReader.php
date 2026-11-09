<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

use InvalidArgumentException;
use Waynestate\Creed\Policy;

/**
 * ADVISOR's one way in to CREED's reader.
 *
 * CREED's Policy (waynestate/creed) is the only code in the stack that decides
 * a version's state, and ADVISOR now asks it directly, so policy_lookup and
 * get_project_status answer what PROCTOR decides. This guard only refuses a
 * schema_version this build does not understand; CREED's own check refuses a
 * missing one. PolicyReaderTest pins the boundary days, so a rule change in
 * CREED shows up here as a red test. One reader, one policy, no twins.
 */
final class PolicyReader
{
    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): Policy
    {
        if (($data['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('The bundled policy has an unsupported schema_version. ADVISOR 0.1 reads schema_version 1.');
        }

        /** @var array<string, mixed> $data */
        return Policy::fromArray($data);
    }
}
