{{-- Momentum harness: $momentum->props($entry) from Blade (?slug=) --}}
@php($entry = \CraftCms\Cms\Entry\Elements\Entry::find()->section('momentumKitchen')->slug(request('slug'))->one())
<script id="props" type="application/json">{!! $entry ? json_encode($momentum->props($entry), JSON_HEX_TAG) : 'null' !!}</script>
<p id="component">{{ $entry ? $momentum->componentFor($entry) : '' }}</p>
