<?php
namespace App\Console\Commands;
use App\Domain\Inventory\InventoryService;
use App\Models\Character;
use App\Models\Item;
use Illuminate\Console\Command;
use Throwable;
class AddInventoryItemCommand extends Command {
 protected $signature='game:inventory:add {characterId} {itemCode} {quantity}'; protected $description='Add a stackable catalog item to a character inventory in local/testing';
 public function handle(InventoryService $inventory){if(!app()->environment(['local','testing'])){$this->error('This command is only available in local or testing.');return 1;}$character=Character::find($this->argument('characterId'));if(!$character){$this->error('Character not found.');return 1;}$item=Item::where('code',$this->argument('itemCode'))->first();if(!$item){$this->error('Item not found.');return 1;}$raw=$this->argument('quantity');if(filter_var($raw,FILTER_VALIDATE_INT)===false||(int)$raw<=0){$this->error('Quantity must be a positive integer.');return 1;}try{$entry=$inventory->addItem($character,$item,(int)$raw);}catch(Throwable $e){$this->error($e->getMessage());return 1;}$this->info('Item added: '.$entry->itemName());$this->line('Total: '.$entry->totalQuantity());$this->line('Locked: '.$entry->lockedQuantity());$this->line('Available: '.$entry->availableQuantity());return 0;}
}
