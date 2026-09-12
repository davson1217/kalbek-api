import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

import Login from './Pages/Auth/Login';
import CharactersIndex from './Pages/Characters/Index';
import Dashboard from './Pages/Dashboard';
import LanguagesIndex from './Pages/Languages/Index';
import ScenariosIndex from './Pages/Scenarios/Index';
import ScenariosShow from './Pages/Scenarios/Show';
import UnitsIndex from './Pages/Units/Index';

const appName = import.meta.env.VITE_APP_NAME || 'Kalbek API';
const pages: Record<string, ComponentType<any>> = {
    'Auth/Login': Login,
    'Characters/Index': CharactersIndex,
    Dashboard,
    'Languages/Index': LanguagesIndex,
    'Scenarios/Index': ScenariosIndex,
    'Scenarios/Show': ScenariosShow,
    'Units/Index': UnitsIndex,
};

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const page = pages[name];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page;
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element was not found.');
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
    },
});
