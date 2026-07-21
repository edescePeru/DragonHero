<ul class="nav flex-column">
  <li class="px-4 py-2"><small class="nav-text">A4gamesDH</small></li>
  <li><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="ti ti-home"></i><span class="nav-text">Inicio</span></a></li>
  <li><a class="nav-link {{ request()->routeIs('characters.overview') ? 'active' : '' }}" href="{{ $navigationCharacter ? route('characters.overview',$navigationCharacter) : route('characters.create') }}"><i class="ti ti-user"></i><span class="nav-text">Mi personaje</span></a></li>
  <li><a class="nav-link {{ request()->routeIs('characters.select*') ? 'active' : '' }}" href="{{ route('characters.select') }}"><i class="ti ti-switch-horizontal"></i><span class="nav-text">Cambiar personaje</span></a></li>
  <li><a class="nav-link {{ request()->routeIs('worlds.*','regions.*','zones.*') ? 'active' : '' }}" href="{{ route('worlds.index') }}"><i class="ti ti-world"></i><span class="nav-text">Mundo</span></a></li>
  <li><a class="nav-link {{ (request()->routeIs('world-maps.*') || request()->routeIs('world-maps.index','world-maps.show','world-maps.world','world-maps.region')) ? 'active' : '' }}" href="{{ route('world-maps.index') }}"><i class="ti ti-map-2"></i><span class="nav-text">Mapas</span></a></li>
  @if($canAdministerContent)
  <li><a class="nav-link {{ request()->routeIs('admin.content.*') ? 'active' : '' }}" href="{{ route('admin.content.items.index') }}"><i class="ti ti-settings"></i><span class="nav-text">Administrar contenido</span></a></li>
  @endif
  <li class="px-4 pt-4 pb-2"><small class="nav-text">Cuenta</small></li>
  <li><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="nav-link border-0 bg-transparent w-100 text-start"><i class="ti ti-logout"></i><span class="nav-text">Cerrar sesión</span></button></form></li>
</ul>
