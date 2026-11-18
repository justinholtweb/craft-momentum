{{-- Momentum root template, Blade flavour (uses Inertia's own directives) --}}
<!DOCTYPE html>
<html lang="{{ $currentSite->language }}">
<head>
  <meta charset="utf-8">
  <!-- momentum-blade-root -->
  @if (! empty($viteEntries)) @vite($viteEntries) @endif
  @inertiaHead
</head>
<body>
  @inertia
</body>
</html>
