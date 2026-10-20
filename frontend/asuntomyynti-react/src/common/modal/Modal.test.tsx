import { render, screen } from '@testing-library/react';
import Modal from './Modal';

test('renders modal', () => {
  render(<Modal isVisible children={<h1>test</h1>} />);

  expect(screen.getByText('test')).toBeDefined();
});

test('Should render null as isVisible is not set', () => {
  render(<Modal children={<h1>test</h1>} />);

  expect(screen.queryByText('test')).toBeNull();
});
