<?php

namespace App\Domain\Characters\Progression;

use App\Domain\Characters\Progression\Exceptions\CharacterProgressionConfigurationConflict;
use App\Models\Character;
use App\Models\CharacterLevelRequirement;
use App\Models\CharacterProgressionRevision;
use App\Models\CharacterProgressionSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CharacterProgressionConfigurationService
{
    public function update(User $administrator, array $input)
    {
        $curve=$this->normalizeCurve(isset($input['curve'])?$input['curve']:[]);
        $cap=$this->positiveInteger(isset($input['max_character_level'])?$input['max_character_level']:null,'Invalid maximum Character level.');
        $version=$this->positiveInteger(isset($input['version'])?$input['version']:null,'Invalid configuration version.');
        $reason=trim(isset($input['reason'])?(string)$input['reason']:'');
        if($reason===''||mb_strlen($reason)>1000)throw new InvalidArgumentException('A reason between 1 and 1000 characters is required.');
        $this->validateCurve($curve,$cap,1);

        return DB::transaction(function()use($administrator,$curve,$cap,$version,$reason){
            $setting=CharacterProgressionSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            if((int)$setting->version!==$version)throw new CharacterProgressionConfigurationConflict('La configuración fue modificada por otro administrador. Recarga la página.');
            $previousCap=(int)$setting->max_character_level;
            $requirements=CharacterLevelRequirement::orderBy('level')->lockForUpdate()->get();
            $previous=$requirements->map(function($row){return['level'=>(int)$row->level,'required_experience'=>(int)$row->required_experience];})->all();
            $highest=(int)Character::max('level');
            $this->validateCurve($curve,$cap,max(1,$highest));
            $previousLast=(int)$requirements->max('level');
            $newLast=(int)array_key_last($curve);
            $this->validateTailRemoval($previousLast,$newLast,$previousCap,$highest);
            $this->persistCurve($requirements,$curve,$newLast);
            $setting->max_character_level=$cap;$setting->version=(int)$setting->version+1;$setting->updated_by=$administrator->id;$setting->save();
            CharacterProgressionRevision::create(['administrator_user_id'=>$administrator->id,'previous_max_level'=>$previousCap,'new_max_level'=>$cap,'previous_curve'=>$previous,'new_curve'=>$this->snapshot($curve),'reason'=>$reason]);
            return$setting;
        },3);
    }

    private function normalizeCurve(array $rows)
    {
        $curve=[];
        foreach($rows as$row){if(!is_array($row))throw new InvalidArgumentException('Invalid progression row.');$level=$this->positiveInteger(isset($row['level'])?$row['level']:null,'Invalid progression level.');if(isset($curve[$level]))throw new InvalidArgumentException('Progression levels cannot be duplicated.');$curve[$level]=$this->nonNegativeInteger(isset($row['required_experience'])?$row['required_experience']:null,'Invalid required experience.');}
        ksort($curve,SORT_NUMERIC);return$curve;
    }
    private function validateCurve(array $curve,$cap,$highestCharacter)
    {
        if(empty($curve)||!isset($curve[1])||$curve[1]!==0)throw new InvalidArgumentException('Progression must start at level 1 with zero experience.');
        $previous=-1;$expected=1;foreach($curve as$level=>$experience){if($level!==$expected++)throw new InvalidArgumentException('Progression levels must be continuous.');if($experience<0||$experience<=$previous)throw new InvalidArgumentException('Progression experience must be strictly increasing.');$previous=$experience;}
        $last=(int)array_key_last($curve);if($cap<1||$cap>$last)throw new InvalidArgumentException('Maximum Character level exceeds the configured curve.');if($cap<$highestCharacter)throw new InvalidArgumentException('Maximum Character level cannot be lower than an existing Character level.');
    }
    private function validateTailRemoval($previousLast,$newLast,$activeCap,$highestCharacter)
    {
        if($newLast>=$previousLast)return;
        if($newLast<1||$newLast<$activeCap)throw new InvalidArgumentException('Only final levels above the active maximum may be removed.');
        if($newLast<$highestCharacter)throw new InvalidArgumentException('A progression level used by a Character cannot be removed.');
    }
    private function persistCurve($requirements,array $curve,$newLast)
    {
        CharacterLevelRequirement::where('level','>',$newLast)->delete();
        $existing=$requirements->keyBy('level');
        $changed=[];
        foreach($curve as$level=>$experience){if($existing->has($level)&&(int)$existing->get($level)->required_experience!==$experience)$changed[]=$level;}
        if(!empty($changed))CharacterLevelRequirement::whereIn('level',$changed)->delete();
        $now=now();$rows=[];
        foreach($curve as$level=>$experience){if(!$existing->has($level)||in_array($level,$changed,true))$rows[]=['level'=>$level,'required_experience'=>$experience,'created_at'=>$now,'updated_at'=>$now];}
        if(!empty($rows))CharacterLevelRequirement::insert($rows);
    }
    private function positiveInteger($value,$message){$value=$this->nonNegativeInteger($value,$message);if($value<1)throw new InvalidArgumentException($message);return$value;}
    private function nonNegativeInteger($value,$message){if(is_int($value)&&$value>=0)return$value;if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]*)$/',$value)||$this->greaterThanPhpInt($value))throw new InvalidArgumentException($message);return(int)$value;}
    private function greaterThanPhpInt($value){$max=(string)PHP_INT_MAX;return strlen($value)>strlen($max)||(strlen($value)===strlen($max)&&strcmp($value,$max)>0);}
    private function snapshot(array $curve){$rows=[];foreach($curve as$level=>$experience)$rows[]=['level'=>$level,'required_experience'=>$experience];return$rows;}
}
