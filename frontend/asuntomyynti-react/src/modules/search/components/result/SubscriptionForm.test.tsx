import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';

import SubscriptionForm from './SubscriptionForm';
import singleProject from '../../mocks/single-project.json';
import { Project } from '../../../../types/common';

const project = singleProject as unknown as Project;

test('renders SubscriptionForm component', () => {
  const { container } = render(
    <BrowserRouter>
      <SubscriptionForm project={project} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders form inputs', () => {
  render(
    <BrowserRouter>
      <SubscriptionForm project={project} />
    </BrowserRouter>
  );

  expect(screen.queryByText('SEARCH:email')).not.toBeNull();
  expect(screen.queryByText('SEARCH:subscribe-to-newsletter')).not.toBeNull();
  expect(screen.queryByText('SEARCH:cancel')).not.toBeNull();
});
