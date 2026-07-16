<?php
namespace App\Domain\Inventory;use App\Models\Item;use InvalidArgumentException;
final class ItemClassification{const STACKABLE='stackable';const UNIQUE='unique';public function classify(Item $item):string{$max=(int)$item->max_stack;if($item->is_stackable===true&&$max>1)return self::STACKABLE;if($item->is_stackable===false&&$max===1)return self::UNIQUE;throw new InvalidArgumentException('Inconsistent Item stack classification.');}}
