<script setup>
import { Deferred, Head, Link } from '@inertiajs/vue3';
defineProps({ entry: Object, site: Object, alternates: Array });
</script>

<template>
  <Head :title="entry.title" />
  <main id="momentum-kitchen" :data-entry-id="entry.id">
    <h1 id="momentum-title">{{ entry.title }}</h1>
    <p id="momentum-heading">{{ entry.fields.momentumHeading }}</p>
    <img v-for="img in entry.fields.momentumImage" :key="img.id" :src="img.transforms?.momentumThumb ?? img.url" :alt="img.alt" />
    <template v-if="entry.fields.momentumBody">
      <template v-for="(chunk, i) in entry.fields.momentumBody.chunks" :key="i">
        <div v-if="chunk.type === 'markup'" class="momentum-markup" v-html="chunk.html" />
        <aside v-else class="momentum-nested">{{ chunk.entry?.fields.momentumHeading }}</aside>
      </template>
    </template>
    <section v-for="block in entry.fields.momentumBlocks" :key="block.id" :class="`block-${block.type}`">
      {{ block.title }}
    </section>
    <Deferred data="entry.fields.momentumRelated">
      <template #fallback><p id="momentum-loading">Loading related…</p></template>
      <ul id="momentum-related">
        <li v-for="rel in entry.fields.momentumRelated" :key="rel.id"><Link :href="rel.url">{{ rel.title }}</Link></li>
      </ul>
    </Deferred>
    <nav id="momentum-alternates">
      <a v-for="alt in alternates" :key="alt.site" :href="alt.url" :hreflang="alt.language">{{ alt.site }}</a>
    </nav>
  </main>
</template>
