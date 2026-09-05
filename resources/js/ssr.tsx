import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { TooltipProvider } from '@/components/ui/tooltip';
import { modulePageLoaders } from './modules-pages.generated';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Page registry: platform pages plus module-package pages (P4
 * self-containment). Module globs come from modules-pages.generated.ts
 * (run `php artisan modules:pages` to regenerate).
 */
const pageLoaders = {
    ...import.meta.glob('./pages/**/*.tsx'),
    ...modulePageLoaders,
};

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(`./pages/${name}.tsx`, pageLoaders),
        setup: ({ App, props }) => {
            return (
                <TooltipProvider delayDuration={0}>
                    <App {...props} />
                </TooltipProvider>
            );
        },
    }),
);
