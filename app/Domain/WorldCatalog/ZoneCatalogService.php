<?php
namespace App\Domain\WorldCatalog;
use App\Models\Zone;
use App\Models\ZoneConnection;
final class ZoneCatalogService {
    public function zoneDetail(Zone $zone){
        $active=CatalogStatus::ACTIVE;
        $zone=Zone::query()->whereKey($zone->getKey())->where('status',$active)->whereHas('region',function($q)use($active){$q->where('status',$active)->whereHas('world',function($w)use($active){$w->where('status',$active);});})->with(['region.world','monsters'=>function($q)use($active){$q->where('monsters.status',$active)->wherePivot('status',$active)->orderBy('name');},'outgoingConnections'=>function($q)use($active){$q->where('status',$active)->with(['toZone'=>function($z)use($active){$z->where('status',$active);},'requiredItem']);},'incomingConnections'=>function($q)use($active){$q->where('status',$active)->with(['fromZone'=>function($z)use($active){$z->where('status',$active);},'requiredItem']);}])->firstOrFail();
        $explicitReverseIds=ZoneConnection::query()->where('from_zone_id',$zone->id)->where('status',$active)->pluck('to_zone_id')->all();
        $zone->setRelation('outgoingConnections',$zone->outgoingConnections->filter(function($connection){return $connection->toZone!==null;})->values());
        $zone->setRelation('incomingConnections',$zone->incomingConnections->filter(function($connection)use($explicitReverseIds){return $connection->fromZone!==null&&($connection->is_bidirectional||in_array($connection->from_zone_id,$explicitReverseIds,true));})->values());
        return $zone;
    }
}
