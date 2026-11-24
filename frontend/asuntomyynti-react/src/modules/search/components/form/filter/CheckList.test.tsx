import { render, screen } from '@testing-library/react';
import CheckList from './CheckList';
import { BrowserRouter } from 'react-router-dom';

const mockItems = ['for_sale', 'pre_marketing', 'processing', 'ready'];
const mockLabel = 'State of sale';
const mockName = 'project_state_of_sale' as any;

test('renders CheckList component', () => {
  const { container } = render(
    <BrowserRouter>
      <CheckList items={mockItems} name={mockName} label={mockLabel} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders four checklist items', () => {
  render(
    <BrowserRouter>
      <CheckList items={mockItems} name={mockName} label={mockLabel} />
    </BrowserRouter>
  );

  expect(screen.getByText('ES:for_sale')).toBeDefined();
  expect(screen.getByText('ES:pre_marketing')).toBeDefined();
  expect(screen.getByText('ES:processing')).toBeDefined();
  expect(screen.getByText('ES:ready')).toBeDefined();
});

test('renders label', () => {
  render(
    <BrowserRouter>
      <CheckList items={mockItems} label={mockLabel} name={mockName} />
    </BrowserRouter>
  );

  expect(screen.getByText('State of sale')).toBeDefined();
});
