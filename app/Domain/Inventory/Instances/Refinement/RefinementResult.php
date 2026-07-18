<?php

namespace App\Domain\Inventory\Instances\Refinement;

use InvalidArgumentException;

final class RefinementResult
{
    private $data;

    public function __construct(array $data, $replayed = false)
    {
        $data = $this->normalizeHistoricalSuccess($data);
        $required = ['schema_version','operation_uuid','character_id','item_instance_id','item_instance_uuid','item_id','item_code','item_name','from_level','attempted_to_level','current_level','refinement_rule_id','success_chance_basis_points','roll','failure_behavior','gold_consumed','materials_consumed','instance_status','attempted_at','result'];
        if (array_diff($required, array_keys($data))) throw new InvalidArgumentException('Incomplete refinement result payload.');
        if (! in_array($data['result'], ['succeeded', 'failed'], true)) throw new InvalidArgumentException('Invalid refinement result.');
        $materials = array_map(function ($line) { return ['item_id'=>(int)$line['item_id'],'item_code'=>$line['item_code'],'item_name'=>$line['item_name'],'quantity'=>(int)$line['quantity']]; }, $data['materials_consumed']);
        usort($materials, function ($a, $b) { return $a['item_id'] <=> $b['item_id']; });
        $success = $data['result'] === 'succeeded';
        $message = $success ? 'Refinamiento exitoso. El objeto subió a +'.(int)$data['current_level'].'.' : 'El refinamiento falló. El objeto permanece en +'.(int)$data['current_level'].'.';
        $this->data = array_merge($data, ['success'=>$success,'materials_consumed'=>$materials,'message'=>$message,'replayed'=>(bool)$replayed]);
    }

    private function normalizeHistoricalSuccess(array $data)
    {
        if (! isset($data['attempted_to_level']) && isset($data['to_level'])) $data['attempted_to_level'] = $data['to_level'];
        if (! isset($data['failure_behavior'])) $data['failure_behavior'] = RefinementFailureBehavior::KEEP_LEVEL;
        if (! isset($data['result'])) $data['result'] = 'succeeded';
        return $data;
    }

    public function toArray() { return $this->data; }
    public function success() { return $this->data['success']; }
    public function result() { return $this->data['result']; }
    public function currentLevel() { return (int) $this->data['current_level']; }
    public function replayed() { return $this->data['replayed']; }
    public function message() { return $this->data['message']; }
}
