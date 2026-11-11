import * as Sentry from '@sentry/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter as Router } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import './i18n';

import DataContextProvider from './modules/search/DataContext';
import ProjectApartmentsWidget from './modules/search/widgets/ProjectApartmentsWidget';
import SearchContainer from './modules/search/SearchContainer';

import 'hds-core/lib/components/button/button.css';
import './index.scss';

Sentry.init({
  dsn: import.meta.env.VITE_SENTRY_DSN,
  integrations: [Sentry.browserTracingIntegration()],

  // Set tracesSampleRate to 1.0 to capture 100%
  // of transactions for performance monitoring.
  // We recommend adjusting this value in production
  tracesSampleRate: 0.25,
});

const projectContainer = document.getElementById('asu_react_project_apartments');
const searchContainer = document.getElementById('asu_react_search');
const projectSettings =
  (window as any).drupalSettings?.asu_apartment_search?.project_apartment_widget;

const urlParams = new URLSearchParams(window.location.search);
const devProjectUuid = urlParams.get('project_uuid');
const devOwnershipType =
  urlParams.get('project_ownership_type') ||
  import.meta.env.VITE_PROJECT_OWNERSHIP_TYPE ||
  'hitas';
const devStateOfSale = urlParams.get('state_of_sale') || urlParams.get('project_state_of_sale');

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
});

const renderApp = (container: HTMLElement, content: JSX.Element) => {
  const root = createRoot(container);
  root.render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <Router>
          <DataContextProvider>
            {content}
          </DataContextProvider>
        </Router>
      </QueryClientProvider>
    </StrictMode>
  );
};

// Prefer Drupal-injected project widget when embedded.
if (projectContainer && projectSettings?.project_uuid) {
  renderApp(
    projectContainer,
    <ProjectApartmentsWidget
      projectUuid={projectSettings.project_uuid}
      projectOwnershipType={projectSettings.project_ownership_type || 'hitas'}
      projectStateOfSale={projectSettings.project_state_of_sale}
    />
  );
}
// Dev convenience: localhost with project_uuid query renders project widget.
else if (searchContainer && devProjectUuid) {
  renderApp(
    searchContainer,
    <ProjectApartmentsWidget
      projectUuid={devProjectUuid}
      projectOwnershipType={devOwnershipType}
      projectStateOfSale={devStateOfSale || undefined}
    />
  );
} else if (searchContainer) {
  renderApp(searchContainer, <SearchContainer />);
}
