<?php

namespace App\Domain\GameHome;

use App\Domain\Characters\Accounts\ActiveCharacterContext;
use App\Domain\Shops\ShopAvailabilityService;
use App\Models\GameHomeCard;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class GameHomeCardDestinationResolver
{
    private $context;
    private $routes;
    private $availability;
    private $characters = [];
    private $fallbacks = [];
    private $shops = [];
    private $shopsPrimed = false;

    public function __construct(ActiveCharacterContext $context, GameHomeCardRouteCatalog $routes, ShopAvailabilityService $availability)
    {
        $this->context = $context;
        $this->routes = $routes;
        $this->availability = $availability;
    }

    public function primeShops($cards)
    {
        $this->shopsPrimed = true;
        $ids = $cards->filter(function ($card) { return $card->destination_type === GameHomeCardDestinationType::SHOP; })
            ->pluck('destination_value')->filter()->map(function ($id) { return (int) $id; })->unique();
        if ($ids->isEmpty()) return;
        $this->shops = Shop::with(['npc', 'locations'])->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    public function globalShopOptions()
    {
        $now = CarbonImmutable::now('UTC');
        return Shop::with(['npc', 'locations'])->whereDoesntHave('locations')->orderBy('name')->get()
            ->filter(function ($shop) use ($now) { return $this->availability->isShopVisible($shop, $now); })
            ->map(function ($shop) { return ['id' => (int) $shop->id, 'name' => $shop->name, 'npc_name' => $shop->npc->name, 'status' => $shop->status]; })->values()->all();
    }

    public function resolve(GameHomeCard $card, User $user)
    {
        $type = $card->destination_type;
        if (! GameHomeCardDestinationType::supports($type)) throw new InvalidArgumentException('Tipo de destino no válido.');
        if ($type === GameHomeCardDestinationType::CHARACTER_SELECTOR) return route('characters.select');
        if ($type === GameHomeCardDestinationType::ACTIVE_CHARACTER_OVERVIEW) {
            $character = $this->activeCharacter($user);
            return $character ? route('characters.overview', $character) : $this->characterFallback($user);
        }
        if ($type === GameHomeCardDestinationType::EXTERNAL_URL) return $this->external($card->destination_value);
        if ($type === GameHomeCardDestinationType::SHOP) {
            $shop = isset($this->shops[(int) $card->destination_value]) ? $this->shops[(int) $card->destination_value] : ($this->shopsPrimed ? null : Shop::with(['npc', 'locations'])->find($card->destination_value));
            if (! $shop || $shop->locations->isNotEmpty() || ! $this->availability->isShopVisible($shop, CarbonImmutable::now('UTC'))) throw new InvalidArgumentException('La tienda global no está disponible.');
            $character = $this->activeCharacter($user);
            return $character ? route('characters.shops.show', [$character, $shop]) : $this->characterFallback($user);
        }
        $definition = $this->routes->definition($card->destination_value);
        if (! $definition || ! \Route::has($card->destination_value)) throw new InvalidArgumentException('Ruta interna no aprobada.');
        if ($definition['parameters'] === GameHomeCardRouteCatalog::NONE) return route($card->destination_value);
        $character = $this->activeCharacter($user);
        return $character ? route($card->destination_value, $character) : $this->characterFallback($user);
    }

    public function validateConfiguration($type, $value)
    {
        if (! GameHomeCardDestinationType::supports($type)) throw new InvalidArgumentException('Tipo de destino no válido.');
        if (! GameHomeCardDestinationType::requiresValue($type)) {
            if ($value !== null && $value !== '') throw new InvalidArgumentException('Este destino no admite un valor manual.');
            return null;
        }
        if ($type === GameHomeCardDestinationType::ROUTE) {
            if (! $this->routes->supports($value) || ! \Route::has($value)) throw new InvalidArgumentException('Ruta interna no aprobada.');
            return $value;
        }
        if ($type === GameHomeCardDestinationType::SHOP) {
            if (! preg_match('/^[1-9][0-9]*$/', (string) $value)) throw new InvalidArgumentException('Selecciona una tienda global válida.');
            $shop = Shop::with(['npc', 'locations'])->find($value);
            if (! $shop || $shop->locations->isNotEmpty() || ! $this->availability->isShopVisible($shop, CarbonImmutable::now('UTC'))) throw new InvalidArgumentException('Selecciona una tienda global disponible.');
            return (string) $shop->id;
        }
        return $this->external($value);
    }

    private function activeCharacter(User $user){if(!array_key_exists($user->id,$this->characters))$this->characters[$user->id]=$this->context->current($user);return$this->characters[$user->id];}
    private function characterFallback(User $user){if(!isset($this->fallbacks[$user->id]))$this->fallbacks[$user->id]=$user->characters()->where('status','active')->exists()?route('characters.select'):route('characters.create');return$this->fallbacks[$user->id];}
    private function external($url){if(!is_string($url)||$url!==trim($url)||!filter_var($url,FILTER_VALIDATE_URL))throw new InvalidArgumentException('URL externa no válida.');$scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));if(!in_array($scheme,['http','https'],true))throw new InvalidArgumentException('Solo se permiten URLs HTTP o HTTPS.');return$url;}
}
