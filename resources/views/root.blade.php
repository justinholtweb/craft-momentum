{{-- Momentum's Inertia root view: hands the page to the site's Twig root template. --}}
{!! app(\JustinHoltWeb\Momentum\View\RootRenderer::class)->render($page) !!}
