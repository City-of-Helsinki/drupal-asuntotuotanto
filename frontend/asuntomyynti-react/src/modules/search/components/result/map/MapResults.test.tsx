
import { render, screen } from '@testing-library/react';
import MapResults from './MapResults';

const defaultProps = {
  searchResults: [],
  closeMap: () => {},
  header: '',
  config: undefined,
  currentLang: 'fi',
};

test('renders MapResults component', () => {
  const { container } = render(<MapResults {...defaultProps} />);
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders MapResults map component', () => {
  render(<MapResults {...defaultProps} />);
  const result = screen.getByText('OpenStreetMap', { exact: false });
  expect(result).toBeDefined();
});

test('renders MapResults header', () => {
  render(<MapResults {...defaultProps} header={'test header'} />);
  const result = screen.getByText('test header');
  expect(result).toBeDefined();
});

test('renders show as list button', () => {
  render(<MapResults {...defaultProps} />);
  const element = screen.getByText('SEARCH:show-as-list');
  expect(element).toBeDefined();
});

