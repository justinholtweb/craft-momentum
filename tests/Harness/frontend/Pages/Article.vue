<script setup>
import { Link, Head } from '@inertiajs/vue3';
defineProps({ entry: Object, site: Object });
</script>

<template>
  <Head :title="entry.title" />
  <main id="momentum-article" :data-entry-id="entry.id">
    <p id="momentum-site">{{ site.name }}</p>
    <h1 id="momentum-title">{{ entry.title }}</h1>
    <p id="momentum-summary">{{ entry.fields.summary }}</p>
    <ul>
      <li v-for="rel in entry.fields.related" :key="rel.id">
        <Link :href="rel.url" class="momentum-related">{{ rel.title }}</Link>
      </li>
    </ul>
  </main>
</template>
