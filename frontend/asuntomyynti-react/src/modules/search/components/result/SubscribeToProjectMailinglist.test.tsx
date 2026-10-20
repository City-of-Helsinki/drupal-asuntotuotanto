import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';

import SubscribeToProjectMailinglist from './SubscribeToProjectMailinglist';

import mockConfig from '../../mocks/filter-config.json';
import mockProject from '../../mocks/single-project.json';
import { DataConfig, Project } from '../../../../types/common';

const project = mockProject as unknown as Project;
const config = mockConfig as unknown as DataConfig;

const mockUserConfig = {
  user_id: '123',
  email_address: null,
  username: '',
  applications: [],
  followed_projects: [],
};

const mockUserConfigWithSubscriptions = {
  user_id: '123',
  email_address: null,
  username: '',
  applications: [],
  followed_projects: [99],
};

test('renders SubscribeToProjectMailinglist component', () => {
  const { container } = render(
    <BrowserRouter>
      <SubscribeToProjectMailinglist project={project} config={config} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders unsubscribe button when user is already following the project', () => {
  render(
    <BrowserRouter>
      <SubscribeToProjectMailinglist
        project={{ ...project, id: 99 }}
        config={{ ...config, user: mockUserConfigWithSubscriptions }}
      />
    </BrowserRouter>
  );
  expect(screen.getByText('SEARCH:project-mailinglist-unsubscribe', { exact: false }));
});

test('renders subscribe button when user does not already follow the project', () => {
  render(
    <BrowserRouter>
      <SubscribeToProjectMailinglist
        project={{ ...project, id: 10 }}
        config={{ ...config, user: mockUserConfig }}
      />
    </BrowserRouter>
  );
  expect(screen.getByText('SEARCH:project-mailinglist-subscribe', { exact: false }));
});
