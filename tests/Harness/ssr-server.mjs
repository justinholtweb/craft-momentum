// A real Inertia SSR server (Vue 3 + @inertiajs/vue3/server) for Momentum's harness checks.
// Components are render functions, so it runs without a Vite SSR build:
//   cp tests/Harness/ssr-server.mjs <harness>/storage/momentum-ssr.mjs
//   ddev exec "node storage/momentum-ssr.mjs"            (listens on 13714)
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createInertiaApp, Head } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';

const page = (id) => ({
    props: { entry: Object, site: Object },
    setup(props) {
        return () => h('main', { id, 'data-entry-id': props.entry.id }, [
            h(Head, { title: `SSR ${props.entry.title}` }),
            h('p', { id: 'momentum-site' }, props.site.name),
            h('h1', { id: 'momentum-title' }, props.entry.title),
        ]);
    },
});

const pages = {
    'Pages/Article': page('momentum-article'),
    'Pages/Kitchen': page('momentum-kitchen'),
};

const render = await createInertiaApp({
    resolve: (name) => pages[name],
    setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
});

createServer((p) => render(p, renderToString), { port: Number(process.env.PORT || 13714) });
