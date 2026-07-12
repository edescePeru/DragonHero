<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Crear personaje - A4gamesDH</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') }}">
  <script type="module" src="{{ asset('assets/js/main.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
</head>
<body>
  <div class="container d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="card" style="max-width: 560px; width: 100%;">
      <div class="card-body p-5">
        <div class="text-center mb-4">
          <img src="{{ asset('assets/images/logo.svg') }}" alt="A4gamesDH" class="mb-4">
          <h1 class="h3 mb-2">Crea tu personaje</h1>
          <p class="text-secondary mb-0">Elige el nombre con el que comenzarás tu aventura.</p>
        </div>
        @if ($errors->any())
          <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('characters.store') }}">
          @csrf
          <div class="mb-4">
            <label for="name" class="form-label">Nombre del personaje</label>
            <input id="name" name="name" type="text" maxlength="32" value="{{ old('name') }}"
              class="form-control @error('name') is-invalid @enderror" required autofocus>
            <div class="form-text">Entre 3 y 32 caracteres: letras, números, espacios, guion o guion bajo.</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Crear personaje</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
          @csrf
          <button type="submit" class="btn btn-link text-secondary">Cerrar sesión</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
