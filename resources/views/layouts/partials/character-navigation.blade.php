<div class="card mb-4"><div class="card-body py-2"><nav class="nav nav-pills flex-column flex-sm-row" aria-label="Secciones del personaje">
  <a class="nav-link {{ request()->routeIs('characters.show') ? 'active' : '' }}" href="{{ route('characters.show',$character) }}">Resumen y estadísticas</a>
  <a class="nav-link {{ request()->routeIs('characters.inventory.*') ? 'active' : '' }}" href="{{ route('characters.inventory.index',$character) }}">Inventario</a>
  <a class="nav-link {{ request()->routeIs('characters.wallet.*') ? 'active' : '' }}" href="{{ route('characters.wallet.show',$character) }}">Billetera</a>
</nav></div></div>
