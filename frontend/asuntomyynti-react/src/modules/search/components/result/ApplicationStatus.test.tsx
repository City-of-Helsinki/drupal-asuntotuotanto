import { render, screen } from '@testing-library/react';
import ApplicationStatus from './ApplicationStatus';
import { BrowserRouter } from 'react-router-dom';

test('renders ApplicationStatus component', () => {
  const { container } = render(
    <BrowserRouter>
      <ApplicationStatus status="LOW" />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders ApplicationStatus as Vacant', () => {
  render(<ApplicationStatus status="VACANT" />);
  expect(screen.getByText('SEARCH:apartment-free')).toBeDefined();
});

test('renders ApplicationStatus as Reserved', () => {
  render(<ApplicationStatus status="RESERVED" />);
  expect(screen.getByText('SEARCH:apartment-reserved')).toBeDefined();
});

test('renders ApplicationStatus as Reserved', () => {
  render(<ApplicationStatus status="RESERVED_HASO" />);
  expect(screen.getByText('SEARCH:apartment-reserved')).toBeDefined();
});

test('renders ApplicationStatus as Sold', () => {
  render(<ApplicationStatus status="SOLD" />);
  expect(screen.getByText('SEARCH:apartment-sold')).toBeDefined();
});

test('renders ApplicationStatus as low', () => {
  render(<ApplicationStatus status="LOW" />);
  expect(screen.getByText('SEARCH:apartment-few-applications')).toBeDefined();
});

test('renders ApplicationStatus as medium', () => {
  render(<ApplicationStatus status="MEDIUM" />);
  expect(screen.getByText('SEARCH:apartment-some-applications')).toBeDefined();
});

test('renders ApplicationStatus as high', () => {
  render(<ApplicationStatus status="HIGH" />);
  expect(screen.getByText('SEARCH:apartment-lots-of-applications')).toBeDefined();
});

test('renders ApplicationStatus default status of "no applications"', () => {
  render(<ApplicationStatus status="" />);
  expect(screen.getByText('SEARCH:apartment-no-applications')).toBeDefined();
});

test('renders ApplicationStatus with labelOverride', () => {
  render(<ApplicationStatus status="LOW" labelOverride="OVERRIDE_LABEL" />);
  expect(screen.getByText('OVERRIDE_LABEL')).toBeDefined();
});
