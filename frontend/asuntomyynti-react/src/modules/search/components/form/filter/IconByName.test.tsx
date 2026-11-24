import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import IconByName from './IconByName';

test('renders IconByName component', () => {
  const { container } = render(
    <BrowserRouter>
      <IconByName name="location" />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders IconByName with icon IconLocation', () => {
  render(<IconByName name={'location'} />);
  const element = screen.getByTestId('icon-location');
  expect(element).toBeDefined();
});

test('renders IconByName with icon IconHome', () => {
  render(<IconByName name={'home'} />);
  const element = screen.getByTestId('icon-home');
  expect(element).toBeDefined();
});

test('return null from empty icon name', () => {
  render(<IconByName name={''} />);
  const element = screen.queryByRole('img');
  expect(element).toBeNull();
});
