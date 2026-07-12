<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>{{ $character->name }} - A4gamesDH</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') }}">
  <script type="module" src="{{ asset('assets/js/main.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
</head>
<body>
  <nav class="navbar bg-white border-bottom fixed-top topbar px-3">
    <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm">Dashboard</a>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-light btn-sm">Cerrar sesión</button></form>
  </nav>
  <aside id="sidebar" class="sidebar">
    <div class="logo-area"><a href="{{ route('dashboard') }}"><img src="{{ asset('assets/images/logo.svg') }}" alt="A4gamesDH"></a></div>
    <ul class="nav flex-column">
      <li class="px-4 py-2"><small class="nav-text">Personaje</small></li>
      <li><a class="nav-link" href="{{ route('dashboard') }}"><i class="ti ti-home"></i><span class="nav-text">Dashboard</span></a></li>
      <li><a class="nav-link active" href="{{ route('characters.show', $character) }}"><i class="ti ti-user"></i><span class="nav-text">Ficha</span></a></li>
    </ul>
  </aside>
  <main id="content" class="content py-10">
    <div class="container-fluid">
      <div class="mb-4"><h1 class="fs-3 mb-1">{{ $character->name }}</h1><p class="text-secondary mb-0">Ficha inicial del personaje</p></div>
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="card h-100"><div class="card-body p-4 text-center">
            <div class="icon-shape icon-xl bg-primary bg-opacity-10 text-primary rounded-circle mx-auto mb-3"><i class="ti ti-shield fs-2"></i></div>
            <h2 class="h4 mb-1">{{ $character->name }}</h2>
            <span class="badge bg-success-subtle text-success">{{ ucfirst($character->status) }}</span><hr>
            <div class="d-flex justify-content-between"><span>Nivel</span><strong>{{ $character->level }}</strong></div>
            <div class="d-flex justify-content-between mt-2"><span>Experiencia</span><strong>{{ $character->experience }}</strong></div>
          </div></div>
        </div>
        <div class="col-lg-8">
          <div class="card"><div class="card-header"><h2 class="h5 mb-0">Estadísticas base</h2></div><div class="card-body p-4">
            <div class="mb-4"><div class="d-flex justify-content-between mb-2"><span>Vida</span><strong>{{ $character->current_health }} / {{ $character->base_max_health }}</strong></div>
              <div class="progress"><div class="progress-bar bg-danger" style="width: 100%"></div></div></div>
            <div class="row g-3">
              <div class="col-md-6"><div class="border rounded p-3 d-flex justify-content-between"><span>Ataque</span><strong>{{ $character->base_attack }}</strong></div></div>
              <div class="col-md-6"><div class="border rounded p-3 d-flex justify-content-between"><span>Defensa</span><strong>{{ $character->base_defense }}</strong></div></div>
              <div class="col-md-6"><div class="border rounded p-3 d-flex justify-content-between"><span>Precisión</span><strong>{{ $character->base_accuracy }}</strong></div></div>
              <div class="col-md-6"><div class="border rounded p-3 d-flex justify-content-between"><span>Evasión</span><strong>{{ $character->base_evasion }}</strong></div></div>
              <div class="col-md-6"><div class="border rounded p-3 d-flex justify-content-between"><span>Crítico</span><strong>{{ $character->base_critical_rate }}%</strong></div></div>
              <div class="col-md-6"><div class="border rounded p-3 d-flex justify-content-between"><span>Estado</span><strong>{{ $character->status }}</strong></div></div>
            </div>
          </div></div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
