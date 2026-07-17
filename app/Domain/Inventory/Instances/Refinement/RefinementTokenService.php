<?php

namespace App\Domain\Inventory\Instances\Refinement;

use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RefinementTokenService
{
    const SCHEMA_VERSION = 1;

    public function issue(Character $character, ItemInstance $instance)
    {
        return Crypt::encryptString(json_encode([
            'schema_version' => self::SCHEMA_VERSION,
            'character_id' => (int) $character->id,
            'item_instance_uuid' => $instance->uuid,
            'observed_refinement_level' => (int) $instance->refinement_level,
            'operation_uuid' => (string) Str::uuid(),
        ]));
    }

    public function decode($token)
    {
        try { $data = json_decode(Crypt::decryptString($token), true); } catch (DecryptException $exception) { throw new InvalidArgumentException('Invalid refinement token.'); }
        $required = ['schema_version', 'character_id', 'item_instance_uuid', 'observed_refinement_level', 'operation_uuid'];
        if (! is_array($data) || array_diff($required, array_keys($data)) || count($data) !== count($required) || (int) $data['schema_version'] !== self::SCHEMA_VERSION || ! Str::isUuid($data['item_instance_uuid']) || ! Str::isUuid($data['operation_uuid'])) throw new InvalidArgumentException('Invalid refinement token.');
        return $data;
    }
}
