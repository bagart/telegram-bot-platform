import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { modulePageLoaders } from './modules-pages.generated';
import '../css/app.css';

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

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, pageLoaders),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <TooltipProvider delayDuration={0}>
                    <App {...props} />
                </TooltipProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
