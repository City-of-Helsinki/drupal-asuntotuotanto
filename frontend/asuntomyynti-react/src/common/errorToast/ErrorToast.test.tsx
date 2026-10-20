import { render, screen } from '@testing-library/react';
import ErrorToast from './ErrorToast';

test('renders ErrorToast', () => {
  render(<ErrorToast />);

  expect(screen.getByText('SEARCH:something-wrong')).toBeDefined();
});
